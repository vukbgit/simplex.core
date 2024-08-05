<?php
declare(strict_types = 1);

namespace Simplex\Authentication;

use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Simplex\VanillaCookieExtended;
use Aura\Auth\Session\Segment;
use Aura\Auth\AuthFactory;
use Aura\Auth\Exception\UsernameNotFound;
use Aura\Auth\Exception\PasswordIncorrect;
use Aura\Auth\Verifier\PasswordVerifier;
use function Simplex\slugToPSR1Name;
use function Simplex\loadLanguages;

/**
 * Routing Middleware that uses nikic/fastroute
 * Based on Middlewares\FastRoute with the addition of routes definition processing and additionl route parameters outside of route patterns
 *
 * @author vuk <info@vuk.bg.it>
 */
class Middleware implements MiddlewareInterface
{
    /**
    * Auth factory instance
    * @var \Aura\Auth\AuthFactory
    **/
    protected $authFactory;
    
    /**
    * @var ContainerInterface
    * DI container, to create/get instances of classes needed at runtime (such as models)
    */
    protected $DIContainer;
    
    /**
    * @var VanillaCookieExtended
    * cookies manager
    */
    protected $cookie;
    
    /**
    * @var ServerRequestInterface
    */
    private $request;
    
    /*
    * Authentication area
    * @var string
    */
    private $area;
    
    /*
    * @var array
    * Actions that can be called by routes
    */
    private $actions = ['sign-in','verify','sign-out'];
    
    /*
    * @var array
    * Methods that can be used to sign in
    */
    private $signInMethods = ['htpasswd', 'db', 'custom'];
    
    /*
    * @var string
    * Persistent logins table
    */
    private $persistentLoginsTableName = 'persistent_logins';
    
    /*
    * @var string
    * Persistent logins key length
    */
    private $persistentLoginsKeyBinLength = 12;
    
    /**
    * Constructor
    * @param ContainerInterface $DIContainer
    * @param VanillaCookieExtended $cookie
    */
    public function __construct(
        ContainerInterface $DIContainer,
        VanillaCookieExtended $cookie
    )
    {
      session_set_cookie_params([
        //'lifetime' => $cookie_timeout,
        'path' => SESSION_COOKIE_PATH ? sprintf('/%s/', SESSION_COOKIE_PATH) : '/',
        //'domain' => $cookie_domain,
        'secure' => SESSION_COOKIE_SECURE ?? true,
        'httponly' => true,
        'samesite' => 'None'
      ]);
        session_start();
        $this->DIContainer = $DIContainer;
        $this->cookie = $cookie;
    }
    
    /**
     * Sets auth factory instance
     **/
    protected function setAuthFactory()
    {
        $sessionSegment = new Segment($this->area);
        $this->authFactory = new AuthFactory($_COOKIE, null, $sessionSegment);
    }
    
    /**
     * Process a server request and return a response.
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->request =& $request;
        //check mandatory route parameters
        $routeParameters = $request->getAttributes()['parameters'];
        $mandatoryParameters = ['area'];
        foreach ($mandatoryParameters as $parameter) {
            if(!isset($routeParameters->$parameter) || !$routeParameters->$parameter) {
                throw new \Exception(sprintf('Current route definition MUST contain a \'handler\'[1][\'authentication\']->%s parameter', $parameter));
            }
        }
        $this->area = $routeParameters->area;
        $authenticationParameters = $routeParameters->authentication;
        $mandatoryParameters = ['action', 'urls'];
        foreach ($mandatoryParameters as $parameter) {
            if(!isset($authenticationParameters->$parameter) || !$authenticationParameters->$parameter) {
                throw new \Exception(sprintf('Current route definition MUST contain a \'handler\'[1][\'authentication\']->%s parameter', $parameter));
            }
        }
        //check action
        if(!in_array($authenticationParameters->action, $this->actions)) {
            throw new \Exception(sprintf('Authentication action \'%s\' not allowed', $authenticationParameters->action));
        }
        //process urls
        foreach($authenticationParameters->urls as &$url) {
          $url = $this->parseAuthenticationRoute($url);
        }
        $routeParameters->authentication = $authenticationParameters;
        $request = $request->withAttribute('parameters', $routeParameters);
        //perform action
        $this->setAuthFactory();
        $this->{slugToPSR1Name($authenticationParameters->action, 'method')}($authenticationParameters);
        //local processing
        if(isset($authenticationParameters->localProcessing)) {
            $localProcessingHandler = $this->DIContainer->get($authenticationParameters->localProcessing->handler);
            $userData = $this->getUserData();
            //method must take userdata as first argument and return it
            $userData = call_user_func([$localProcessingHandler, $authenticationParameters->localProcessing->method], $userData);
            $this->setUserData((array) $userData);
        }
        //return response
        $response = $handler->handle($request);
        //update response with authentication result
        $authenticationResult = $this->request->getAttributes()['authenticationResult']->{$this->area};
        //redirect
        if($authenticationResult->redirectTo) {
            $response = $response->withHeader('Location', $authenticationResult->redirectTo);
            $response = $response->withStatus(302);
        }
        //cookies
        if(!$authenticationResult->authenticated) {
            $this->cookie->setAreaCookie($this->area, 'authenticationReturnCode', $authenticationResult->returnCode);
        } else {
            $this->cookie->setAreaCookie($this->area, 'authenticationReturnCode', null);
        }
        //handle result
        return $response;
    }
    
    /**
     * Performs sign in
     * @param object $authenticationParameters
     * @return int :
     * 1 = missing field
     * 2 = wrong username
     * 3 = wrong password
     * 4 = correct sign in
     * 5 = custom validation failed
     */
    private function signIn(object $authenticationParameters)
    {
        $returnCode = 0;
        if($authenticationParameters)
        //get input
        $args = array(
            'username' => FILTER_SANITIZE_SPECIAL_CHARS,
            'password' => FILTER_SANITIZE_SPECIAL_CHARS
        );
        $input = filter_input_array(INPUT_POST, $args);
        $username = trim($input['username'] ?? '');
        $password = trim($input['password'] ?? '');
        //check input
        /*if(!$username || !$password) {
            //missing field(s)
            $returnCode = 1;
        } else {*/
            //check urls
            $mandatoryUrls = ['signInForm', 'successDefault'];
            foreach ($mandatoryUrls as $parameter) {
                if(!isset($authenticationParameters->urls->$parameter) || !$authenticationParameters->urls->$parameter) {
                    throw new \Exception(sprintf('Current route definition MUST contain a \'handler\'[1][\'authentication\']->urls->%s parameter', $parameter));
                }
            }
            //check methods
            if(!isset($authenticationParameters->signInMethods) || empty($authenticationParameters->signInMethods)) {
                throw new \Exception('Current route definition MUST contain a \'handler\'[1][\'authentication\']->signInMethods parameter and it MUST not be empty');
            }
            //check users - roles map file path
            if(!isset($authenticationParameters->usersRolesPath) || !is_file($authenticationParameters->usersRolesPath)) {
                throw new \Exception('Current route definition MUST contain a \'handler\'[1][\'authentication\']->usersRolesPath parameter and it MUST be a valid path');
            }
            //check permissions - roles map file path
            if(isset($authenticationParameters->permissionsRolesPath) && !is_file($authenticationParameters->permissionsRolesPath)) {
                throw new \Exception('Current route definition contains a \'handler\'[1][\'authentication\']->permissionsRolesPath parameter but it is NOT a valid path');
            }
            //loop methods
            foreach ($authenticationParameters->signInMethods as $method => $methodProperties) {
                //check method
                if(!in_array($method, $this->signInMethods)) {
                    throw new \Exception(sprintf('Sign in method \'%s\' not allowed', $method));
                }
                switch ($method) {
                    case 'htpasswd':
                        //check input
                        if(!$username || !$password) {
                            //missing field(s)
                            $returnCode = 1;
                            break;
                        } 
                        //check htpasswd file path
                        if(!isset($methodProperties->path)) {
                            throw new \Exception('htpasswd authentication method must have a \'path\' property with path to the htpasswd file');
                        }
                        if(!is_file($methodProperties->path)) {
                            throw new \Exception(sprintf('authentication method htpasswd \'path\' property must be a valid path to a htpasswd file'));
                        }
                        $returnCode = $this->signInWithHtpasswd($username, $password, $methodProperties->path);
                    break;
                    case 'db':
                        //check input
                        if(!$username || !$password) {
                            //missing field(s)
                            $returnCode = 1;
                            break;
                        }
                        //check connection configuration file path
                        if(!isset($methodProperties->path)) {
                            throw new \Exception('db authentication method must have a \'path\' property with path to the database configuration file');
                        }
                        //check algo
                        if(!isset($methodProperties->algo)) {
                            throw new \Exception('db authentication method must have an \'algo\' property with the hashing algorithm as accepted by hash() as first argument');
                        }
                        //check table
                        if(!isset($methodProperties->table)) {
                            throw new \Exception('db authentication method must have a \'table\' property with the name of the db table to query');
                        }
                        //check fields
                        if(!isset($methodProperties->fields) || !is_array($methodProperties->fields) || count($methodProperties->fields) < 3) {
                            throw new \Exception('db authentication method must have a \'fields\' property, an array of columns table names with 3 elements, first is the username field, second the password field, third is user role field');
                        }
                        $returnCode = $this->signInWithDb($username, $password, $methodProperties->path, $methodProperties->algo, $methodProperties->table, $methodProperties->fields, $methodProperties->condition ?? null);
                    break;
                    case 'custom':
                        //check controller
                        if(!isset($methodProperties->handler)) {
                            throw new \Exception('custom authentication method must have a \'handler\' property with controller name to be used by DI-container');
                        }
                        //check controller method
                        if(!isset($methodProperties->handlerMethod)) {
                            throw new \Exception('custom authentication method must have a \'handlerMethod\' property with controller method name to be called');
                        }
                        $returnCode = $this->signInCustom($methodProperties->handler, $methodProperties->handlerMethod);
                    break;
                }
                if($returnCode == 4) {
                    break;
                }
            }
        //}
        switch ($returnCode) {
            //success
            case 4:
                //set user role
                $this->setUserRole($authenticationParameters);
                //load role permissions
                $this->loadPermissionsRoles($authenticationParameters);
                //redirect
                $redirectToAfterLogin = $this->cookie->getAreaCookie($this->area, 'redirectToAfterLogin');
                if($redirectToAfterLogin) {
                    $location = $redirectToAfterLogin;
                    $this->cookie->setAreaCookie($this->area, 'redirectToAfterLogin', null);
                } elseif(isset($this->getUserData()->redirectTo)) {
                    $location = $this->getUserData()->redirectTo;
                } else {
                    $location = $authenticationParameters->urls->successDefault;
                }
                //set authentication status
                $this->setAuthenticationStatus(true, 4, $location);
                //handle persistent login
                if(isset($authenticationParameters->persistentLogin)) {
                  $this->setPersistentLogin();
                }
            break;
            //failure
            default:
                $this->setAuthenticationStatus(false, $returnCode, $authenticationParameters->urls->signInForm);
            break;
        }
        return $returnCode;
    }
    
    /**
     * Checks sign in by htpasswd method
     * @param string $username
     * @param string $password
     * @param string $pathToHtpasswdFile path to htpassword file
     * @return int return code: 2 = wrong username, 3 = wrong password, 4 = sign in correct
     **/
    private function signInWithHtpasswd(string $username, string $password, string $pathToHtpasswdFile): int
    {
        $auth = $this->authFactory->newInstance();
        $htpasswdAdapter = $this->authFactory->newHtpasswdAdapter($pathToHtpasswdFile);
        $loginService = $this->authFactory->newLoginService($htpasswdAdapter);
        try {
            //success
            $userData = [
                'username' => $username,
                'password' => $password
            ];
            $loginService->login($auth, $userData);
            unset($userData['password']);
            $this->setUserData($userData);
            $returnCode = 4;
        } catch(UsernameNotFound $e) {
            //wrong username
            $returnCode = 2;
        } catch(PasswordIncorrect $e) {
            //wrong password
            $returnCode = 3;
        }
        return $returnCode;
    }
    
    /**
     * Checks sign in by db method
     * @param string $username
     * @param string $password
     * @param string $pathToDbConfigFile path to db configuration file
     * @param string $algo hashing algorithm for the hash() function, see https://github.com/auraphp/Aura.Auth PDO Adapter
     * @param string $table table or view to be quieried
     * @param array $fields: username field, password field, any other field
     * @param string $condition: query where condition portion
     * @return int return code: 2 = wrong username, 3 = wrong password, 4 = sign in correct
     **/
    private function signInWithDb(string $username, string $password, string $pathToDbConfigFile, $algo, string $table, array $fields, string $condition = null): int
    {
        //create PDO instance
        $dbConfig = (require $pathToDbConfigFile)[ENVIRONMENT];
        $dsn = sprintf(
            '%s:dbname=%s;host=%s',
            $dbConfig['driver'],
            $dbConfig['database'],
            $dbConfig['host']
        );
        $pdo = new \PDO($dsn, $dbConfig['username'], $dbConfig['password']);
        //create password verifier
        //xx(hash($algo, $password));
        $hash = new PasswordVerifier($algo);
        $auth = $this->authFactory->newInstance();
        //fix problem when the role field is named group (SQL reserved word for GROUP BY)
        $encloseCharacters = [
            'mysql' => '`',
            'pgsql' => '"'
        ];
        $fieldsForPDO = $fields;
        $groupFieldIndex = array_search('group', $fields);
        if($groupFieldIndex !== false) {
            $fieldsForPDO[$groupFieldIndex] = sprintf('%1$s%2$s%1$s', $encloseCharacters[$dbConfig['driver']], $fieldsForPDO[$groupFieldIndex]);
        }
        $pdoAdapter = $this->authFactory->newPdoAdapter($pdo, $hash, $fieldsForPDO, $table, $condition);
        $loginService = $this->authFactory->newLoginService($pdoAdapter);
        try {
            //success
            $loginService->login(
                $auth,
                [
                    'username' => $username,
                    'password' => $password
                ]
            );
            //get role
            $userData = $auth->getUserData();
            $userData['username'] = $username;
            $userData['role'] = $userData[$fields[2]];
            //unset($userData[$fields[2]]);
            $this->setUserData($userData);
            $returnCode = 4;
        } catch(UsernameNotFound $e) {
            //wrong username
            $returnCode = 2;
        } catch(PasswordIncorrect $e) {
            //wrong password
            $returnCode = 3;
        }
        return $returnCode;
    }
    
    /**
     * Checks sign in by a custom method
     * @param string $handlerName
     * @param string $handlerMethod
     * @return int return code: 4 = sign in correct | 5 = custom validation failed
     **/
    private function signInCustom(string $handlerName, string $handlerMethod): int
    {
        $handler = $this->DIContainer->get($handlerName);
        $userData = [];
        $authenticated = call_user_func_array([$handler, $handlerMethod], [$this->request, &$userData]);
        //check controller
        if(!is_bool($authenticated)) {
            throw new \Exception(sprintf('custom authentication handler %s::%s method must return a boolean value', get_class($handler), $handlerMethod));
        }
        //check userdata
        if($authenticated === true && (!isset($userData['username']) || !isset($userData['role']))) {
            throw new \Exception(sprintf('custom authentication handler %s::%s method must take userdata array by reference as second parameter and fill it at least with \'username\' and \'role\' properties', get_class($handler), $handlerMethod));
        }
        if($authenticated) {
            $auth = $this->authFactory->newInstance();
            $loginService = $this->authFactory->newLoginService();
            $loginService->forceLogin($auth, $userData['username'], $userData);
            $returnCode = 4;
        } else {
            $returnCode = $userData['returnCode'] ?? 5;
        }
        return $returnCode;
    }
    
    /**
     * Performs verification
     * @param object $authenticationParameters
     */
    protected function verify(object $authenticationParameters)
    {
      $returnCode = 0;
      $isSignInFormRoute = $this->request->getAttributes()['parameters']->action == 'sign-in-form';
      //check urls
      $mandatoryUrls = ['signInForm', 'signOut'];
      foreach ($mandatoryUrls as $parameter) {
        if(!isset($authenticationParameters->urls->$parameter) || !$authenticationParameters->urls->$parameter) {
          throw new \Exception(sprintf('Current route definition MUST contain a \'handler\'[1][\'authentication\']->urls->%s parameter', $parameter));
        }
      }
      //verify
      if($this->isAuthenticated()) {
        //store userdata into request
        $auth = $this->authFactory->newInstance();
        $this->setUserData();
        //verify from sign-in form
        if($isSignInFormRoute) {
          if(!isset($authenticationParameters->urls->successDefault)) {
            throw new \Exception('Route definition for sign-in-form must include "successDefault" url');
          } else {
            $this->setAuthenticationStatus(true, 4, $this->parseAuthenticationRoute($authenticationParameters->urls->successDefault));
          }
        } else {
        //verify from other route, do no redirect
          $this->setAuthenticationStatus(true, 4);
        }
      } else {
        //check persistent login
        $validPersistentLogin = false;
        if(isset($authenticationParameters->persistentLogin)) {
          $persistentLoginKey = $this->cookie->getAreaCookie($this->area, 'plk');
          //x($persistentLoginKey);
          //cookie with key
          if($persistentLoginKey) {
            $query = $this->DIContainer->get('queryBuilder');
            $persistentLogin = $query
              ->table($this->persistentLoginsTableName)
              ->select([
                'username',
                'userdata',
                $query->raw(sprintf('DATE_ADD(`date`, INTERVAL %d DAY) < NOW() AS expired', $authenticationParameters->persistentLogin->expirationDays))
              ])
              ->where('key', $persistentLoginKey)
              ->first();
            if($persistentLogin) {
              //expired
              if($persistentLogin->expired) {
                //delete
                $query
                  ->table($this->persistentLoginsTableName)
                  ->where('key', $persistentLoginKey)
                  ->delete();
              } else {
                $validPersistentLogin = true;
                //update
                $newPersistentLoginKey = $this->generatePersistentLoginKey();
                $query
                  ->table($this->persistentLoginsTableName)
                  ->where('key', $persistentLoginKey)
                  ->update([
                    'key' => $newPersistentLoginKey,
                    'date' => $query->raw('NOW()'),
                  ]);
                //force authentication
                $userData = (array) json_decode($persistentLogin->userdata);
                $auth = $this->authFactory->newInstance();
                $loginService = $this->authFactory->newLoginService();
                $loginService->forceLogin($auth, $persistentLogin->username, $userData);
                $this->setUserData($userData);
                $this->setAuthenticationStatus(true, 4);
              }
            }
          }
        }
        //xx($validPersistentLogin);
        if(!$validPersistentLogin) {
          //status
          //sign in form without valid authentication (handled above) and persistent login, 
          if($isSignInFormRoute) {
            $this->setAuthenticationStatus(true, 4);
          } else {
            $this->setAuthenticationStatus(false, $returnCode, !isset($authenticationParameters->optional) || !$authenticationParameters->optional ? $authenticationParameters->urls->signInForm : null);
            //store current route for redirect
            $this->cookie->setAreaCookie($this->area, 'redirectToAfterLogin', $this->request->getUri()->getPath());
          }
        }
      }
    }
    
    /**
     * Signs out
     * @param object $authenticationParameters
     **/
    private function signOut(object $authenticationParameters)
    {
      $returnCode = 0;
      //check urls
      $mandatoryUrls = ['signInForm'];
      foreach ($mandatoryUrls as $parameter) {
        if(!isset($authenticationParameters->urls->$parameter) || !$authenticationParameters->urls->$parameter) {
          throw new \Exception(sprintf('Current route definition MUST contain a \'handler\'[1][\'authentication\']->urls->%s parameter', $parameter));
        }
      }
      //sign out
      $logoutService = $this->authFactory->newLogoutService();
      $auth = $this->authFactory->newInstance();
      $logoutService->logout($auth);
      $this->setAuthenticationStatus(false, $returnCode, $authenticationParameters->urls->signInForm);
      //delete persistent login
      if(isset($authenticationParameters->persistentLogin)) {
        $persistentLoginKey = $this->cookie->getAreaCookie($this->area, 'plk');
        //xx($persistentLoginKey);
        //cookie with key
        if($persistentLoginKey) {
          $query = $this->DIContainer->get('queryBuilder');
          $persistentLogin = $query
            ->table($this->persistentLoginsTableName)
            ->where('key', $persistentLoginKey)
            ->delete();
        }
      }
    }
    
    /**
     * Gets authentication status
     **/
    protected function isAuthenticated()
    {
        $auth = $this->authFactory->newInstance();
        //r($auth->isValid());
        return $auth->isValid();
    }
    
    /**
     * Sets authentication status into request
     **/
    protected function setAuthenticationStatus($authenticated, $returnCode = null, $redirectTo = null)
    {
        $this->request = $this->request->withAttribute(
            'authenticationResult', 
            (object) [
                $this->area => (object) [
                    'authenticated' => $authenticated,
                    'returnCode' => $returnCode,
                    'redirectTo' => $redirectTo
                ]
            ]
        );
    }
    
    /**
     * Generates persistent login key
     **/
    private function generatePersistentLoginKey()
    {
      return bin2hex(random_bytes($this->persistentLoginsKeyBinLength));
    }
    
    /**
     * Sets persistent login
     **/
    protected function setPersistentLogin()
    {
      $userData = $this->getUserData();
      //generate persistent login key
      $persistentLoginKey = $this->generatePersistentLoginKey();
      $query = $this->DIContainer->get('queryBuilder');
      //check stored persistent login key
      $oldPersistentLoginKey = $this->cookie->getAreaCookie($this->area, 'plk');
      //check table
      if (!$query->tableExists($this->persistentLoginsTableName)) {
        $keyHexLength = $this->persistentLoginsKeyBinLength * 2;
        $sql = <<<EOT
        CREATE TABLE `persistent_logins` (
          `key` varchar($keyHexLength) NOT NULL,
          `username` varchar(48) NOT NULL,
          `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `userdata` text NOT NULL,
          PRIMARY KEY (`key`)
        );
EOT;
        $query->query($sql);
      }
      if($oldPersistentLoginKey) {
        $persistentLogin = $query
          ->table($this->persistentLoginsTableName)
          ->where('key', $oldPersistentLoginKey)
          ->where('username', $userData->username)
          ->first();
        if($persistentLogin) {
          $query
          ->table($this->persistentLoginsTableName)
          ->where('key', $oldPersistentLoginKey)
          ->update([
            'key' => $persistentLoginKey,
            'date' => $query->raw('NOW()'),
          ]);
        }
      }
      if($oldPersistentLoginKey === null || $persistentLogin === null) {
        //insert
        $query
          ->table($this->persistentLoginsTableName)
          ->insert([
            'key' => $persistentLoginKey,
            'username' => $userData->username,
            'userdata' => json_encode($userData),
          ]);
      }
      //set cookie
      $this->cookie->setAreaCookie($this->area, 'plk', $persistentLoginKey);
    }
    
    /**
     * Sets user data both in session and request
     * @param array $userData: if passed it is stored into session
     **/
    public function setUserData($userData = null)
    {
        //set into session
        if($userData) {
            $auth = $this->authFactory->newInstance();
            $auth->setUserData($userData);
        }
        //set into request
        $this->request = $this->request->withAttribute('userData', $this->getUserData());
    }
    
    /**
     * Gets user data
     **/
    protected function getUserData(): object
    {
        $auth = $this->authFactory->newInstance();
        return (object) $auth->getUserData();
    }
    
    /**
     * Sets user role from users - roles map file
     * @param object $authenticationParameters
     **/
    protected function setUserRole($authenticationParameters)
    {
        //get current userdata
        $userData = $this->getUserData();
        //check if user role has already been set
        if(isset($userData->role)) {
            return;
        }
        //get users roles
        $usersRoles = require $authenticationParameters->usersRolesPath;
        //check it's an object
        if(!is_object($usersRoles)) {
            throw new \Exception(sprintf('File %s must return an object', $authenticationParameters->usersRolesPath));
        }
        //check user role
        if(!isset($usersRoles->{$userData->username})) {
            throw new \Exception(sprintf('A role must be assigned to user \'%s\' into file %s', $userData->username, $authenticationParameters->usersRolesPath));
        }
        //set user role
        $userData->role = $usersRoles->{$userData->username};
        $this->setUserData((array) $userData);
    }
    
    /**
     * Loads permissions for roles from permissions - roles map file
     * @param object $authenticationParameters
     **/
    protected function loadPermissionsRoles($authenticationParameters)
    {
        //get current userdata
        $userData = $this->getUserData();
        //get permissions roles
        $permissionsRoles = require $authenticationParameters->permissionsRolesPath;
        //check it's an object
        if(!is_object($permissionsRoles)) {
            throw new \Exception(sprintf('File %s must return an object', $authenticationParameters->permissionsRolesPath));
        }
        //set user's role permissions
        $userPermissions = [];
        $userRoles = $userData->role;
        if(!is_array($userRoles)) {
          $userRoles = [$userRoles];
        }
        foreach ((array) $permissionsRoles as $permission => $roles) {
            if(!empty(array_intersect($userRoles, $roles))) {
                $userPermissions[] = $permission;
            }
        }
        $userData->permissions = $userPermissions;
        $this->setUserData((array) $userData);
    }
    
    /**
     * Parses an authentication route
     * @param string $route
     **/
    private function parseAuthenticationRoute(string $route)
    {
      //check lang parameter
      $languageCode = $this->request->getAttributes()['parameters']->lang ?? current(get_object_vars(loadLanguages('local')))->{'ISO-639-1'};
      $route = str_replace('{lang}', $languageCode, $route);
      return $route;
    }
}
