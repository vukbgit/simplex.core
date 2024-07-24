//see application template for global variables set from Twig parameters
/*
* Simplex application level javascript functions
*/
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
        var atRegex = RegExp(atReplacement);
        var dotRegex = RegExp(dotReplacement, 'g');
        var emails = $(".obfuscated-email").each(function(){
            obfuscatedEmail = $(this)
                .attr('href')
                .replace(/mailto:/, '');
            email = obfuscatedEmail
                .replace(atRegex, "@")
                //.replace(/dotReplacement/g, ".")
                .replace(dotRegex, ".")
                ;
            $(this).attr('href', 'mailto:' + email);
            if($(this).text() == obfuscatedEmail) {
                $(this).text(email);
            }
        });
    }
}
//window.onload = EmailUnobsfuscate;

/**************
* TRANSLATION *
***************/
/**
 * Gets translation for a text from a master language into all of the others configured languages
 * @param string route: to be called to get translations
 * @param string text to be translated
 * @param callback callback function to pass translations array to
 * @param object extraData any other data to be passed to callback
 */
function translate(route, text, callback, extraData) {
  $.ajax({
    type: "POST",
    url: route,
    data: ({ text : text }),
    dataType: "json",
    success: function(data) {
      callback(data, extraData);
    },
    error: function() {
        alert('Error in translation request');
    }
  });
}
 
