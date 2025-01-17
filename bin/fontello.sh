#!/bin/bash
#bootstrap
source "${BASH_SOURCE%/*}/bootstrap.sh"
PATH_TO_CONFIG_FILE=$1
PATH_TO_CSS=$2
if [ -z "$PATH_TO_CONFIG_FILE" ] || [ -z "$PATH_TO_CSS" ]; then
  outputMessage "E" "Fontello script must be called with 2 parameters with paths form web root for:
- config file generated by fontello.com
- folder to store CSS files into"
  exit;
fi
PATH_TO_CONFIG_FILE=${ABS_PATH_TO_ROOT}/${PATH_TO_CONFIG_FILE}
PATH_TO_CSS=${ABS_PATH_TO_ROOT}/${PATH_TO_CSS}
outputMessage "H" $PATH_TO_CONFIG_FILE
outputMessage "H" $PATH_TO_CSS
outputMessage "H" "importing fontello..."
${ABS_PATH_TO_ROOT}/public/share/node_modules/fontello-cli/bin/fontello-cli install --config ${PATH_TO_CONFIG_FILE} --css ${PATH_TO_CSS}/css --font ${PATH_TO_CSS}/font
outputMessage "H" "resetting session..."
rm ${ABS_PATH_TO_ROOT}/.fontello-session
