/* custom js for the subscription form */

/*jslint indent: 2 */
/*global CRM, ts */

cj(function ($) {
  'use strict';
  $('.crm-recurcontrib-form-block table').append($('#contributionrecur-extra tr'));
  $('.crm-recurcontrib-form-block table').prepend($('#contributionrecur-info tr'));
});