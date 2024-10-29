=== App360 CRM ===
Contributors: App360
Tags: e-commerce, crm, woocommerce, app360
Requires at least: 5.6
Tested up to: 5.8
Stable tag: 1.4.4
License: GPLv2 or later

App360 CRM plugin allows the integration between WooCommerce and App360 CRM

== Description ==

The plugin is completely relying on App360 CRM system as a service, it allows reading/writing being done between two systems.
Therefore, WooCommerce plugin is required.
Major features in App360 CRM plugin include:

* App360 wallet system as payment gateway
* Receiving point reward
* Member tier system
* voucher usage
* App360 API integration

Links:
 * https://www.app360.my/ (App360 CRM Membership System Website)
 * https://www.app360.my/terms-and-conditions/ (Terms and Conditions)
 * https://www.app360.my/privacy-policy/ (Privacy Policy)

== Installation ==
# WooCommerce plugin need to be installed and activated before the installation.

Upload the App360 CRM plugin to your blog, activate it, and then enter your App360 API key in WooCommerce > Settings > Advanced > App360 API.

Generate REST API key in WooCommerce > Settings > Advanced > REST API, enter the consumer key and consumer secret in App360 CRM system setting.

Enter your own wordpress domain in App360 CRM system setting.

1, 2, 3: You're done!

== Changelog ==
= 1.4.4 =
*Release Date - 17 July 2023*

* bugfix  | Prevent Guest use Custom Payment to checkout

= 1.4.3 =
*Release Date - 03 July 2023*

* bugfix  | Encode GET method URL query value for app360 register & login API

= 1.4.2 =
*Release Date - 10 May 2023*

* bugfix  | Change user_login value to include app360 member_id at the end to create unique value

= 1.4.1 =
*Release Date - 07 April 2023*

* bugfix  | Fix variable product using wrong price to calculate tiering discount

= 1.4.0 =
*Release Date - 28 February 2023*

* feature  | New hook to update App360 side order status upon status changes

= 1.3.15 =
*Release Date - 03 February 2023*

* bugfix  | Fix direct call order id property in transaction hook

= 1.3.14 =
*Release Date - 03 February 2023*

* bugfix  | Fix undefined variable

= 1.3.13 =
*Release Date - 18 January 2023*

* revert  | Revert changes in version 1.3.11 and 1.3.12
* logic   | Prevent coupon application for order when not using coupon code in cart

= 1.3.12 =
*Release Date - 17 January 2023*

* bugfix  | Fix incorrect method to get user_id in coupon validity check function

= 1.3.11 =
*Release Date - 16 January 2023*

* bugfix  | Fix error when calling API generate order with voucher applied

= 1.3.10 =
*Release Date - 23 December 2022*

* bugfix  | Set custom rest api /app360/customer/change_password signature generation to use date that generated under Asia/Kuala_Lumpur timezone

= 1.3.9 =
*Release Date - 22 November 2022*

* bugfix  | Change hook trigering app360 transaction creation API when pay with app360 wallet

= 1.3.8 =
*Release Date - 13 October 2022*

* bugfix  | Remove apply coupon prevention when coupon is not created from app360

= 1.3.7 =
*Release Date - 10 October 2022

* bugfix   | fix single site on token generation

= 1.3.6 =
*Release Date - 23 September 2022*

* feature  | Create voucher redeem transaction in app360 when order status change to 'processing'

= 1.3.5 =
*Release Date - 21 September 2022*

* feature  | create redeem transaction on app360 after order payment complete
* feature  | prevent doing migration during plugin activation when capture processed migration before

= 1.3.4 =
*Release Date - 13 September 2022*

* feature  | create transaction on app360 whenever order changed to 'completed'

= 1.3.3 =
*Release Date - 09 September 2022*

* bugfix  | fix bug change order status 'processing' to 'completed'

= 1.3.2 =
*Release Date - 19 August 2022*

* feature | set phone number as default display name during registration

= 1.3.1 =
*Release Date - 16 August 2022*

* feature | custom API endpoint to change customer's password

= 1.3.0 =
*Release DAte - 26 July 2022*

* feature | App360 single sing-on


= 1.2.10 =
*Release Date - 18 July 2022*

* bugfix | resolve issue unable to view order on woocommerce dashboard

= 1.2.9 =
*Release Date - 12 November 2021*

* feature | payment status checking button in admin order list

= 1.2.8 =
*Release Date - 21 October 2021*

* bugfix | security issue (string escape & string sanitize)

= 1.2.7 =
*Release Date - 01 September 2021*

* bugfix | security issue (string escape)

= 1.2.6 =
*Release Date - 30 August 2021*

* bugfix | stamp redemption
* bugfix | make button looks clickable
* bugfix | security issue (string escape)

= 1.2.5 =
*Release Date - 9 August 2021*

* bugfix | call method of registration API

= 1.2.4 =
*Release Date - 7 August 2021*

* feature - user search by app360 user_id (REST API)

= 1.2.3 =
*Release Date - 17 July 2021*

* bugfix - processing payment

= 1.2.2 =
*Release Date - 17 July 2021*

* feature - GKash paylink integration (pay later)

= 1.2.1 =
*Release Date - 14 July 2021*

* feature - whatsapp notification (pay later)

= 1.2.0 =
*Release Date - 10 July 2021*

* feature - allow guest in the system
* feature - whatsapp notification (with order details)

= 1.1.10 =
*Release Date - 28 June 2021*

* bugfix - notify us button missing

= 1.1.9 =
*Release Date - 28 June 2021*

* bugfix - notify us button missing

= 1.1.8 =
*Release Date - 15 June 2021*

* bugfix - claim stamp method (get -> post)

= 1.1.3 =
*Release Date - 26 May 2021*

* bugfix - wallet spending (without mock spend)

= 1.1.2 =
*Release Date - 26 May 2021*

* bugfix - wallet spending

= 1.1.1 =
*Release Date - 5 April 2021*

* adding grey box on dashboard
* adding website link to api settings

= 1.1.0 =
*Release Date - 1 April 2021*

* add voucher listing view
* add stamp listing view
* customer able to notify client the order details via whatsapp

= 1.0.1 =
*Release Date - 29 March 2021*

* add https protocol to member home url

= 1.0.0 =
*Release Date - 15 January 2021*

* spend deduct credit
* gain reward point
* member tier integration
* voucher usage
* api setting