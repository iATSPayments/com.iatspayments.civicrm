/* custom js for uk dd */
/*jslint indent: 2 */
/*global CRM, ts */

function iatsRecurStartRefresh() {
  cj(function ($) {
    'use strict';
     console.log($('#iats-recurring-start-date'));
     $('.is_recur-section').after($('#iats-recurring-start-date'));
  });
}
