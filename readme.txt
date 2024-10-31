=== PMC Gold ===
Contributors: savala 
Tags: woocommerce, payment, gold, DG gold group, PMC Gold
Requires at least: 5.2
Tested up to: 5.8
Stable tag: 2.7.10
Requires PHP: 7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Adds PMC Gold as a payment method in woocommerce, more info [here](https://pmccoingroup.com/what-is-pmccoin). 

== Description ==

What is PMC Gold?
You are purchasing PMC Gold, a digital token provided by DG Capital, exclusively for the purchase of products and services offered by the vendor for this transaction.

The PMC Gold you are purchasing is an Ethereum token that once you authorize the transaction, is securely and directly transferred to the vendor's digital wallet using the Ethereum blockchain system.

PMC Gold is a token offered by DG Capital through an agreement with the vendor to use it solely to facilitate the purchase of vendor’s products and services through a secure clearance and settlement system. PMC Gold is fractional, digital gold and its value as a commodity is backed 100% by physical gold.

Your purchase of PMC Gold, though a separate purchase, can only be used for the purchase of the products you’ve selected to buy from this vendor. Therefore, your authorization instructing DG Capital to transfer the tokens to the vendor is required for the completion of your product purchase.

Your credit card statement will reflect the purchase of PMC Gold once the transaction is authorized by you and approved and completed by your credit card company.

In the unlikely event you encounter any issues with the products you have purchased using PMC Gold, you should reach out to the vendor through its customer service department.

For further information on DG Capital’s [terms and conditions](https://pmccoingroup.com/terms).

== Installation ==

1. Install the plugin through the WordPress plugins screen directly or upload plugin zip on menu 'Plugins->Add New->Upload plugin'.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Click on 'settings' through the 'Plugins' screen or follow Settings->Woocommerce->Payments to see the plugin settings.
4. Insert API token, If you don't know how to generate the API token follow [API Documentation](https://pmccoingroup.com/docs/v1/auth).
5. Select the 'Company' and 'Save Changes' to release the Wallet field.
6. Select 'Wallet' and 'Save Changes' again.
7. Check the 'Enable/Disable Plugin' and 'Save Changes' to enable plugin.
Enjoy!

== Frequently Asked Questions ==

= Why PMC plugin is automatically disabling? =

Please install SSL certificate and configure with Really Simple SSL plugin.

== Screenshots ==

1. The settings panel used to configure the plugin.
2. Normal checkout with PMC Gold.

== Changelog ==

= 2.7.10 =
* Adding precision values to payment request.

= 2.7.9 =
* Reducing the frequency of ssl validation during checkout process.

= 2.7.8 =
* Adding support for debug logs.

= 2.7.7 =
* Code refactoring and optimization

= 2.7.6 =
* Customized the payment description on the vendor page

= 2.7.5 =
* Thumbnail added on the plugin

= 2.7.4 =
* Code optimization

= 2.7.3 =
* Improved checkout experience

= 2.7.2 =
* Improved first time customer checkout experience

= 2.7.1 =
* Updated errors visibility

= 2.7.0 =
* Replaced integrated checkout with spends

= 2.6.9 =
* Zelle Payment Integration on Autocancel by woocommerce

= 2.6.8 =
* Zelle Payment Integration

= 2.6.7 =
* Verbiage changes on plugin

= 2.6.6 =
* Updated the plugin compaitibility with wordpress upto 5.6

= 2.6.5 =
* Graceful rejection of non-USD currencies with payment processing.

= 2.6.4 =
* Fixing the plugin to stop deactivating intermittently
* Fixing Completed orders to not to move back to processing state 

= 2.6.3 =
* Fixing success configuration message

= 2.6.2 =
* Fixing duplicated messages
* Improving plugin setup messages
* Improving code style

= 2.6.1 =
* Fix redirect popup images for phone screen size
* Fix gift certificate
* Code style improvement

= 2.6.0 =
* Adding cache for API data.
* Adding interval for API requests.
* Adjusting patterns for code style.
* Changing methods name for friendly format.

= 2.5.0 =
* Adding callback treatment for the new checkout.
* Updating callback fail message.
* Fixing token gold on message.

= 2.4.0 =
* Adding test mode.
* Changing plugin name. 
* Changing plugin logo.
* Changing checkout popup confirmation.
* Updating checkout url on callback.

= 2.3.0 =
* Support for Woocommerce 4.0.
* Updating message system for admin area.
* Fixing checkout popup total amount update.

= 2.2.8 =
* Fixing auto disable plugin.
* Fixing checkout without fees.
* Adding card flags.
* Improving timeout of api calls.
* Improving code reuse.
* Adjusting some texts and rename icon.

= 2.2.7 =
* Adding select payment options based on company.
* Adding constant to show the plugin name on admin Settings.
* Adding multi site support.
* Adjusting improving return token on redirect transaction.
* Fix adding international fee verification on fee rules method.
* Improving payment validation of request payment. 

= 2.1.6 =
* Fix (what is pmcgold?) URL.

= 2.0.0 =
* Changing structure plugin.

= 1.5.6 =
* admin notices refactored.
