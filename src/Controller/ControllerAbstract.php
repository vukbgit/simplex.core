<?php
declare(strict_types=1);

namespace Simplex\Controller;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Container\ContainerInterface;
use Twig\Environment;
use function Simplex\slugToPSR1Name;
use function Simplex\loadLanguages;

/*
* In this context controller means a class that:
* - is associated as a handler to a route
* - gets invoked by the request handler middleware, so must be callable, so must have an __invoke magic method
* - receives the request object ($this->request) and the parameters defined for the route ($this->routeParameters)
* - if a route parameter named 'action' exists:
*       - the predefined __invoke method tries to call a public or protected method of the concrete class named after that parameter value
*       - the method name SHOULD be camelcased, so the parameter value SHOULD have hypens (-) to separate words
* - otherwise the __invoke method must be override by concrete derived class
*/
abstract class ControllerAbstract
{
    /**
    * @var ServerRequestInterface
    */
    protected $request;

    /**
    * @var ResponseInterface
    */
    protected $response;

    /**
    * @var ContainerInterface
    * DI container, to create/get instances of classes needed at runtime (such as models)
    */
    protected $DIContainer;

    /**
    * @var object
    * values extracted from route parsing
    */
    protected $routeParameters;
    
    /**
    * @var object
    * values extracted from route query string
    */
    protected $queryParameters;

    /**
    * @var string
    * current application area
    */
    protected $area;

    /**
    * @var object
    * object with configured languages objects
    */
    protected $languages = null;
    
    /**
    * @var object
    * object with current language specifications
    */
    protected $language;

    /**
    * @var string
    * action passed by route
    */
    protected $action;

    /**
    * @var bool
    * whether subject needs authentication
    */
    protected $needsAuthentication = false;

    /**
    * Constructor
    * @param ContainerInterface $DIContainer
    * @param ResponseInterface $response
    */
    public function __construct(ContainerInterface $DIContainer, ResponseInterface $response)
    {
        $this->DIContainer = $DIContainer;
        $this->response = $response;
    }

    /**
     * Get invoked by request handler
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        //before action execution
        $this->doBeforeActionExecution($request);
        //verify authentication
        if(!$this->needsAuthentication || $this->verifyAuthentication($request)) {
            //handle action
            $this->handleActionExecution();
        }
        //return response
        return $this->response;
    }

    /**
    * Performs some operations before action execution
    * @param ServerRequestInterface $request
    */
    protected function doBeforeActionExecution(ServerRequestInterface $request)
    {
        //store request
        $this->storeRequest($request);
        //check route parameters
        $this->checkRouteParameters(['action', 'area']);
        //store area
        $this->area = $this->routeParameters->area;
        //store route action
        $this->action = $this->routeParameters->action;
        //check language
        $this->setLanguage();
        //init traits
        $this->initTraits();
        //area runtime script
        $areaRuntimeScript = sprintf('%s_RUNTIME_SCRIPT', strtoupper($this->area));
        if(defined($areaRuntimeScript)) {
          include_once(constant($areaRuntimeScript));
        }
    }
    
    /**
    * Inits used traits searching for magic method __initTrait[trait-name]
    */
    protected function initTraits()
    {
        //loop used traits
        foreach ((array) class_uses($this) as $traitNamespace) {
            //build magic method name
            list($traitName) = array_reverse(explode('\\', $traitNamespace));
            $methodName = sprintf('__initTrait%s', $traitName);
            //call method
            if(method_exists($this, $methodName)) {
                call_user_func([$this, $methodName]);
            }
        };
    }
    
    /**
    * Stores request and related informations
    * @param ServerRequestInterface $request
    */
    protected function storeRequest(ServerRequestInterface $request)
    {
        //store request and its parameters
        $this->request = $request;
        $this->routeParameters = $request->getAttributes()['parameters'];
        $this->queryParameters = (object) $request->getQueryParams();
        $this->needsAuthentication = $this->needsAuthentication($request);
    }

    /**
    * Checks whether subject needs authentication based on request
    * @param ServerRequestInterface $request
    */
    protected function needsAuthentication(ServerRequestInterface $request)
    {
        return isset($request->getAttributes()['parameters']->authentication);
    }

    /**
    * Checks mandatory route parameters
    * @param array $mandatoryParameters array of mandatory parameters to be searched into $this->routeParameters
    */
    protected function checkRouteParameters(array $mandatoryParameters)
    {
        foreach ($mandatoryParameters as $parameter) {
            if(!isset($this->routeParameters->$parameter) || !$this->routeParameters->$parameter) {
                throw new \Exception(sprintf('Current route definition MUST contain a \'handler\'[1]->%s parameter', $parameter));
            }
        }
    }
    
    /**
    * Loads configured language
    */
    protected function loadLanguages()
    {
        if(!$this->languages) {
            //load languages configuration
            $this->languages = loadLanguages('local');
        }
    }
    
    /**
    * Gets & sets language
    * @param string $languageCode: to force a specific language at runtime
    */
    protected function setLanguage($languageCode = null)
    {
        //try to get language form route
        if(!$languageCode) {
            $languageCode = $this->routeParameters->lang ?? null;
        }
        //load configured languages
        $this->loadLanguages();
        //set current language
        $this->language = $this->languages->$languageCode ?? current(get_object_vars($this->languages));
        $languageIETF = sprintf('%s_%s', $this->language->{'ISO-639-1'}, $this->language->{'ISO-3166-1-2'});
        // Set language
        putenv(sprintf('LC_ALL=%s', $languageIETF));
        setlocale(LC_ALL, sprintf('%s.utf8', $languageIETF));
        //add localeconv information to current language object
        $this->language->localeconv = localeconv();
        $domain = 'simplex';
        // Specify the location of the translation tables
        bindtextdomain($domain, sprintf('%s/locales', PRIVATE_LOCAL_DIR));
        bind_textdomain_codeset($domain, 'UTF-8');
        // Choose domain
        textdomain($domain);
    }

    /**
    * Checks if user is currently authenticated
    * @return bool
    */
    protected function isAuthenticated(): bool
    {
        //no autentication needed
        if(!$this->needsAuthentication) {
            return false;
        } else {
            $requestAttributes = $this->request->getAttributes();
            return $requestAttributes['authenticationResult']->{$this->area}->authenticated;
        }
    }
    
    /**
    * Checks if authenticaion is needed and is valid
    * @param ServerRequestInterface $request: needed to check authentication if subject is not handling current route action
    * @return bool
    */
    protected function verifyAuthentication(ServerRequestInterface $request = null): bool
    {
      $request = $this->request ?? $request;
        $requestAttributes = $request->getAttributes();
        $area = $this->area ?? $requestAttributes['parameters']->area;
        return $requestAttributes['authenticationResult']->$area->authenticated;
    }
    
    /**
    * Return authenticated user data (if any)
    * @param ServerRequestInterface $request: needed to check authentication if subject is not handling current route action
    * @return mixed object with user data | null
    */
    protected function getAuthenticatedUserData(ServerRequestInterface $request = null)
    {
      $request = $this->request ?? $request;
        if($this->needsAuthentication($request) && $this->verifyAuthentication($request)) {
            return $request->getAttributes()['userData'] ?? null;
        } else {
            return null;
        }
    }
    
    /**
    * Checks whether current user has a certain permission
    * @param string $permission
    * @param ServerRequestInterface $request: needed to check permission if subject is not handling current route action
    * @return bool
    */
    protected function checkPermission(string $permission, ServerRequestInterface $request = null): bool
    {
      $request = $this->request ?? $request;
      return in_array($permission, $this->getAuthenticatedUserData($request)->permissions ?? []);
    }
    
    /**
    * Checks whether current user has at least one permission among one set
    * @param array $permissions
    * @param ServerRequestInterface $request: needed to check permissions if subject is not handling current route action
    * @return bool
    */
    protected function checkAtLeastOnePermission(array $permissions, ServerRequestInterface $request = null): bool
    {
      $request = $this->request ?? $request;
        foreach ((array) $permissions as $permission) {
            if($this->checkPermission($permission, $request)) {
                return true;
            }
        }
        return false;
    }
    
    /**
    * Handles action execution
    */
    protected function handleActionExecution()
    {
        //action is set
        if($this->action) {
            //build method name
            $methodName = slugToPSR1Name($this->action, 'method');
            //method exists
            if(method_exists($this, $methodName)) {
                //call method
                call_user_func([$this, $methodName]);
            //method does NOT exist
            } else {
                throw new \Exception(sprintf('current route is associated to action "%s" but method "%s" of class "%s" does not exist', $this->action, $methodName, static::class));
            }
        //action is NOT set
        } else {
            throw new \Exception('current route MUST pass an "action" parameter or __invoke() method should be overridden into concrete class');
        }
    }
    
    /**
    * Outputs
    * @param string $route
    */
    protected function output(string $contentType, string $content)
    {
        $response = $this->response->withHeader('Content-Type', $contentType);
        $response->getBody()
            ->write($content);
        $this->response = $response;
    }
    
    /**
    * Redirects to a route
    * @param string $route
    */
    protected function redirect(string $route)
    {
        $this->response = $this->response->withHeader('Location', $route);
        $this->response = $this->response->withStatus(302);
        //xx($this->response);
    }


    
  /***************
  * TRANSLATIONS *
  ****************/
  
  /**
   * Gets translation for a text from a master language into all of the others configured languages 
   * @return array whose keys are languages and values translations
   */
  public function translate()
  {
    $translator = $this->DIContainer->get('translator');
    $translations = [];
    $text = filter_input(INPUT_POST, 'text', FILTER_UNSAFE_RAW);
    foreach ($this->languages as $languageCode => $languageconfig) {
      //source language
      if($languageCode == constant('AUTOMATIC_TRANSLATIONS')->defaultSourceLanguage) {
        $value = $text;
      } else {
        //other languages
        $value = $translator->translate(
          text: $text,
          target: $languageCode
        );
      }
      $translations[$languageCode] = $value;
    }
    $this->output('text/json', json_encode($translations));
  }
}
