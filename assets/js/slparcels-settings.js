/**
 * Parcels management with IL-Post for https://www.israel.com/
 *
 * @category Parcels & Shipping
 * @package  slparcels
 * @author   Developer: Pniel Cohen
 * @author   Company: Trus (https://www.trus.co.il/)
 */

(function($) {
  "use strict";

  $(document).ready(function() {
    var SlparcelsFieldSelectors = {
      mode: "#slparcels_mode",
      slparcels_identity_client: "#slparcels_identity_client",
      slparcels_identity_secret: "#slparcels_identity_secret",
      slparcels_username: "#slparcels_username",
      slparcels_password: "#slparcels_password",
      slparcels_identity_client_sandbox: "#slparcels_identity_client_sandbox",
      slparcels_identity_secret_sandbox: "#slparcels_identity_secret_sandbox",
      slparcels_username_sandbox: "#slparcels_username_sandbox",
      slparcels_password_sandbox: "#slparcels_password_sandbox"
    };
    var getField = function(field) {
      return $(SlparcelsFieldSelectors[field]);
    };

    var getFieldVal = function(field) {
      return getField(field).length ? getField(field).val() : null;
    };

    var hideField = function(field, val) {
      return getField(field).length
        ? getField(field)
            .closest("tr")
            .hide()
        : false;
    };

    var showField = function(field, val) {
      return getField(field).length
        ? getField(field)
            .closest("tr")
            .show()
        : false;
    };

    var updateModeCredentialsFields = function() {
      if (getFieldVal("mode") === "sandbox") {
        hideField("slparcels_identity_client");
        hideField("slparcels_identity_secret");
        hideField("slparcels_username");
        hideField("slparcels_password");
        showField("slparcels_identity_client_sandbox");
        showField("slparcels_identity_secret_sandbox");
        showField("slparcels_username_sandbox");
        showField("slparcels_password_sandbox");
      } else {
        showField("slparcels_identity_client");
        showField("slparcels_identity_secret");
        showField("slparcels_username");
        showField("slparcels_password");
        hideField("slparcels_identity_client_sandbox");
        hideField("slparcels_identity_secret_sandbox");
        hideField("slparcels_username_sandbox");
        hideField("slparcels_password_sandbox");
      }
    };

    $(document).on(
      "change",
      SlparcelsFieldSelectors.mode,
      updateModeCredentialsFields
    );
    updateModeCredentialsFields();
  });
})(jQuery);
