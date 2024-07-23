#bootstrap
source $PATH_TO_INI_CONFIG
source ${ABS_PATH_TO_ROOT}/private/share/packagist/vukbgit/simplex/bin/bootstrap.sh
#path form web root to Fontello config file as exporteb from fontello.com
PATH_TO_CONFIG_FILE=public/share/fontello.json
#path form web root to folder to store fontello CSS into
PATH_TO_CSS=public/share/fontello
${ABS_PATH_TO_ROOT}/private/share/packagist/vukbgit/simplex/bin/fontello.sh $PATH_TO_CONFIG_FILE $PATH_TO_CSS