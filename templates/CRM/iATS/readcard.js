(function ($) {

  Drupal.behaviors.cfisReadcard = {
    attach: function (context, settings) {

      // KG rewritten from jquery.cardwipe - to accommodate output of encrypted USB reader

      // Parses raw scan into name and ID number
      var companyCardParser = function (rawData) {

        alert('2oe');
        alert(rawData);

        // RegExp to extract the first and last name and ID number from the raw data
        // var pattern = new RegExp("^%B654321[0-9]{10}\\^([A-Z ]+)\/([A-Z ]+)\\^0*([A-Z0-9])+\\?");
        var match = rawData.split('^');

        //var match = pattern.exec(rawData);

        alert(match[0]);
        alert(match[1]);
        alert(match[2]);

        alert('uo');

        if (!match)
          return null;

        var cardData = {
          first: $.trim(match[1]),
          second: $.trim(match[0]),
          idNumber: match[2]
        };
        return cardData;
      };

      // Called on a good scan (company card recognized)
      var goodScan = function (cardData) {
        // var text = ['Success!\nFirst: ', cardData.first, '\nSecond: ', cardData.second, '\nID number: ', cardData.idNumber].join('');
        var text =  ['Success!\nID number: ', cardData.idNumber].join('');
        alert(text);
      };

      // Called on a bad scan (company card not recognized)
      var badScan = function() {
        alert('Card not recognized.');
      };

      // Initialize the plugin.
      $.cardswipe({
        parser: companyCardParser,
        success: goodScan,
        error: badScan
      });


    }

  };

})(jQuery);
