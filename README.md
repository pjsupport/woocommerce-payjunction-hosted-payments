# Description
*This module allows the Woocommerce shopping cart (a plugin for WordPress) to accept payments using the PayJunction Hosted Payments service.*
*This service redirects the user to a secure checkout page hosted by PayJunction for greater security and a reduction in PCI-DSS scope.*
*This module is meant to replace the previous pjsupport/woocommerce_rest module using the API.*

## Disclaimer:                                                                
THERE IS NO WARRANTY FOR THE PROGRAM. THE COPYRIGHT HOLDERS AND/OR OTHER PARTIES PROVIDE THE PROGRAM "AS IS" WITHOUT WARRANTY OF ANY KIND,  EITHER EXPRESSED OR IMPLIED, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE.      
THE ENTIRE RISK AS TO THE QUALITY AND PERFORMANCE OF THE PROGRAM IS WITH YOU. SHOULD THE PROGRAM PROVE DEFECTIVE, YOU ASSUME THE COST OF ALL NECESSARY SERVICING, REPAIR OR CORRECTION.

## Author
Matthew E. Cooper

# Setup 
1. Download the __latest release__ as a __zip__ file
1. On a working WordPress site with Woocommerce, __log into the administration interface__, e.g. https://<your_word_press_site_domain>/wp-admin
1. In the WordPress admin, go to __Plugins > Add New__
1. Click the __Upload Plugin__ button at the top left of this page
1. __Select the zip file__ from step 1 and then click __Install Now__
1. If the plugin installs without errors, continue by clicking the __Activate__ link
1. Continue to _Testing/Development Settings_ or _Production Settings_ below for further instructions

## Testing/Development Settings
1. Go to __Woocommerce > Settings__ from the wp admin menu
1. Select the __Checkout__ tab 
1. Just below the tabs at the top of the page click the __PayJunction Hosted Payments__ link

Make sure the following settings are enabled or filled in:
* __Enable__
* __Enable Test Mode__
* _Optional_ Enable Debugging Mode
* __SANDBOX Hosted Payments Shop Name__
  * _Optional_ Replace the default shop name with a self-created shop for PayJunctionLabs.com
* __SANDBOX API Login__
  * _Optional_ Replace the default login with a self-created API login for PayJunctionLabs.com
* __SANDBOX API Password__
  * _Optional_ Replace the default password with a self-created API password for PayJunctionLabs.com

## Production Settings
1. Go to __Woocommerce > Settings__ from the wp admin menu
1. Click the __Checkout__ tab 
1. Just below the tabs at the top of the page click __PayJunction Hosted Payments__

Make sure the folowing settings are enabled or filled in:
* __Enable__
* __UNCHECK Enable Test Mode__ if it is currently checked.
* _Optional_ Enable Debugging Mode. This is only recommended for troubleshooting.
* __Production Hosted Payments Shop Name__
* __Production API Login__
* __Production API Password__

Click __Save changes__ at the bottom of the page if any updates were made after following the steps above.
