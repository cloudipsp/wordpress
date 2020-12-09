=== FONDY — WooCommerce Payment Gateway ===
Contributors: fondyeu
Tags: payments, payment gateway, woocommerce, online payment, merchant
Requires at least: 3.5
Tested up to: 5.5
Requires PHP: 5.4
Stable tag: 2.6.10
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Description ==
The plugin for WooCommerce allows you to integrate the online payment form on the Checkout page of your online store.

The [FONDY](https://fondy.eu/en/) ecommerce plugin for online stores, based on the WooCommerce addon for CMS Wordpress provides businesses with a single platform for quick and secure accepting payments on the site, and its customers with convenient ways to pay for goods and services of their interest. After connecting the system, your customers will be able to pay for purchases using bank cards, online banking, mobile payments. 

The ability to pay in a convenient way increases customer loyalty, increases the frequency of purchases and helps entrepreneurs earn more. The FONDY platform has already been connected to more than 8000 entrepreneurs around the world — from small start-ups and niche stores to international companies with millions of turnover.

We already work in [33 countries](https://docs.fondy.eu/docs/page/supported-countries/), accept payments from any country, and support more than [100 currencies](https://docs.fondy.eu/docs/page/27/), cooperate with banks of the European Union, Eastern Europe, Ukraine and Russia, and constantly expand our presence around the world.


== Reasons to choose FONDY ==
* We accept payments in all the countries in 100 currencies
* A wide range of payment methods: credit cards, local means of payment, Internet banks
* Support for recurring payments — regular debit from the client card for subscription services
* Holding system — freezing money on the client’s card for up to 25 days with the possibility of debit or refund in 1 click
* Tokenization — automatic filling in the details of the client card upon re-entry
* Roles system — the ability to create users with different access rights to the personal account
* Maximum security level: three levels of anti-fraud protection, SSL/TLS encryption, 3D Secure technology
* [Detailed analytics](https://fondy.eu/en/personal-cabinet/#analytics) on payments and invoices, the formation of customized reports in the user's personal account
* Support for integration with online cash registers (for the region of Russia)


== Supported payment methods ==

= Bank cards =
* Visa, MasterCard, Maestro, American Express, Discover, JCB ([full list](https://docs.fondy.eu/docs/page/payment_methods/))

= Alternative payment methods =
* Trustpay, Alipay, WeChat Pay, Safetypay, iDEAL, SEPA, Skrill ([full list](https://docs.fondy.eu/docs/page/payment_methods/))

= Internet banking =
* Banks in 26 countries ([full list](https://docs.fondy.eu/docs/page/supported-countries/))


== Platform features and benefits ==

= Easy to get started =
Fast and friendly onboarding is one of the key advantages of FONDY. You just need to register in the personal account of the platform, enter payment information, sign an electronic agreement and undergo an express audit of the site by our specialists.

The procedure takes from 1 hour to 2 days. After that you can accept payments from the customers, create online invoices in your account and engage in business development.

= Payment security =
To ensure a high level of security and availability of the platform we placed it in a cloud service that meets the top ten security standards, has protection against DDoS attacks and ensures that there is no physical access by unauthorized persons and organizations to the data and equipment.

Every year we pass PCI DSS certification. We also developed and constantly update our own anti-fraud system consisting of three levels: barrier, analytical, post-operator. This means that the fraudsters have no chance.

= Customer care =
When they first pay, the clients enter the card data and selects their favourite method of payment, the platform will save them in encrypted form. All the following payments will be made for your customers in 1 click, without having to enter any data and fill in the fields. Such care removes unnecessary barriers to purchase, the customers do not have to look for a card or recall the CVV code.

For those who regularly buy goods or purchase subscription services in FONDY, you can set up periodic debiting of money from the card (account) once a week/month/year using the payment calendar. The client will not have to bother with repeated payments, and you will always receive money on time.

= Flexible payment options =
It so happens that the client paid the order and the goods were not in stock. The seller gets into an awkward situation. For such cases FONDY offers a holding mechanism — freezing money in a client’s account for a while. If there is no product, you can return the money back to the client in 1 click.

There are many cases when you need to check the client’s solvency or freeze a certain amount on his account. Often holding is used by car rental services, hotels, delivery services, taxi services.

= Mobile payments =
FONDY is fully optimized for use on smartphones, tablets, laptops, desktops, TVs. Your customers will be comfortable to make purchases from any device.

We also took care of the sellers, they can access the personal account via the web interface or a convenient mobile application for [Android](https://play.google.com/store/apps/details?id=com.cloudipsp.fondyportal&hl=en) and [iOS](https://itunes.apple.com/ua/app/fondy-merchant-portal/id1273277350?l=en). From the application you can view the statistics, generate invoices, work with payments.

= Smart analytics =
We help the sellers get to better know their customers. The built-in analytics system allows real-time tracking of the status of all the payments, to see at what stage of the purchase each customer is.

The system also provides analytics on customers, showing in which ways payment is most often made, from which devices, countries and in what currency. The received data can be viewed in your FONDY account or converted into reports and saved to a computer.


== Screenshots ==
1. Plugin settings
2. Plugin settings
3. Plugin switching-on
4. Order status
5. Payment window in your account on the site
6. Payment page as part of the site design
7. Separate payment page on the side of FONDY
8. Failed payment
9. Successful payment


== Tariffs ==
Only commission with payment. It is possible to adjust payment of the commission on itself, or to impose on the client.

[See current tariffs on the FONDY website](https://fondy.eu/en/tariffs-fondy/)


== FAQ ==
Some answers you can find here [FAQ](https://fondy.eu/en/faq/)


== Installation instructions for the plugin ==

= 1. Module installation =

There are two ways to install the plugin:

1.    Download FONDY payment acceptance plugin for WooCommerce from the WordPress add-ons directory. Unpack this plugin into the /wp-content/plugins/ directory. After that activate it in the “Plugins” menu.
2.    Use the installation on the link replacing “site.com” with the address of your site: [site.com/wp-admin/plugin-install.php?tab=plugin-information&plugin=fondy-woocommerce-payment-gateway](http://site.com/wp-admin/plugin-install.php?tab=plugin-information&plugin=fondy-woocommerce-payment-gateway)


= 2. Module activation =

Go to the Wordpress control panel, find the FONDY payment module in the “Plugins” menu. Click on the “Activate”.

= 3. Settings =

To set up the payment acceptance plugin, do the following:

1.	Go to “WooCommerce” > Settings > Payments.
2. Go to the management of “FONDY” → Management. Let the plugin use this payment method: click “Enable”.
3. Enter the data you received from FONDY company. (can be found in your merchant's technical settings) You need to fill in two fields — Merchant ID and Merchant secretkey.
4. Choose how the payment will be displayed:
	a. Payment page within the site design
	b. Payment page in the personal account on the site
	c. Separate payment page on the side of FONDY
5. Select the Answer page — the page to which the user will be redirected after making the payment, the so-called “Thank you page”.
6. Set what order status should be returned after successful/unsuccessful payment.
7. Save the settings.

Done, now you can accept payments from the customers!


== Changelog ==

= 2.6.10 =
Fix answer to callback
Fix connecting translation files
= 2.6.9 =
Added some API request error handler
= 2.6.8 =
Refund fix
= 2.6.7 =
Added subscription
= 2.6.5 =
Added pre-orders
= 2.6.3 =
Fixed default options
= 2.5.8 =
New logo and testing mode
= 2.5.6 =
Added order statuses to settings page
= 2.5.3 =
Styles moved to merchant portal
= 2.5.2 =
Added instant redirect
= 2.4.5 =
Fix for php 5.3 <
= 2.4.4 =
Fixed checkout card
= 2.4.3 =
Added Refund function
= 2.4.2 =
some fix php tags
= 2.4.1 =
Added js Mask CCard
= 2.4.0 =
Added v2 js Api
= 2.3.0 =
some fix, duplicate update
= 2.2.3 =
change payment complete status
= 2.2 =
stability update
= 2.0 =
change to host-to-host
= 1.0.1 =
add default success page
= 1.0.0 = 
* First release

== Upgrade Notice ==

= 2.5.3 =
Styles moved to merchant portal
= 2.5.2 =
Added instant redirect
= 2.5.0 =
Added token caching
= 2.4.9 =
Added multi currencies support(WMPL)
= 2.4.8 =
Stability update
= 2.4.7 =
Unification css containers
= 2.4.6 =
Order notify update
= 2.4.4 =
Fixed checkout card
= 2.4.3 =
Added Refund function
= 2.4.2 =
some fix php tags
= 2.4.1 =
Added js Mask CCard
= 2.4.0 =
Added v2 js Api
= 2.3.0 =
some fix, duplicate update
= 2.2.3 =
change payment complete status
= 2.2.2 =
add expired callback
= 2.2 =
stability update
= 2.0 =
change to host-to-host
= 1.0.1 =
add default success page
= 1.0.0 =
Add pop-up mode