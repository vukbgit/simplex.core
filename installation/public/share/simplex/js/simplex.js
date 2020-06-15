//see application template for global variables set from Twig parameters
/*
* Simplex application level javascript functions
*/
/**********
* COOKIES *
**********/
/**
* Gets the area cookie and return it as an object
* @param string area
* @param string propertyName
**/
getAreaCookie = function(area, propertyName)
{
    areaCookie = Cookies.getJSON(area);
    if(typeof areaCookie === 'undefined') {
        areaCookie = {};
    }
    if(propertyName) {
        return areaCookie['propertyName'];
    }
    return areaCookie;
}

/**
* Sets a property into the area cookie
* @param string area
* @param string propertyName
* @param mixed propertyValue
**/
setAreaCookie = function(area,  propertyName, propertyValue)
{
    areaCookie = getAreaCookie(area);
    areaCookie[propertyName] = propertyValue;
    var path = '/' + area;
    Cookies.set(area, areaCookie, { expires: cookieDurationDays });
}
/********
* EMAIL *
********/
/**
 * converts an email written in obfuscated style into html back to proper syntax
 * Email must be put into html code with this syntax:
 * @ -> (at)
 * . -> dot
 * spaces are trimmed
 */
function EmailUnobsfuscate(atReplacement, dotReplacement) {
    if(!atReplacement || !dotReplacement) {
        alert('for mail obfuscation to be used, MAIL_AT_REPLACEMENT and MAIL_DOT_REPLACEMENT constants must be defined');
    } else {
        // find all links in HTML
        var obfuscatedEmail, email;
        var emails = $(".obfuscated-email").each(function(){
            obfuscatedEmail = $(this)
                .attr('href')
                .replace(/mailto:/, '');
            email = obfuscatedEmail
                .replace(atReplacement, "@")
                .replace(dotReplacement, ".")
                ;
                $(this).attr('href', 'mailto:' + email);
            if($(this).text() == obfuscatedEmail) {
                $(this).text(email);
            }
        });
    }
}
//window.onload = EmailUnobsfuscate;
