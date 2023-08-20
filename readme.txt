=== Bitcart for WooCommerce ===
Contributors: bitcart
Tags: bitcoin,cryptocurrency,bitcart,accept bitcoin,bitcoin plugin, bitcoin payment processor, bitcoin e-commerce, Lightning Network, Litecoin, Gravity, Bitcoin Cash, bitcoincash
Requires at least: 3.9
Tested up to: 6.1
Requires PHP: 5.4
Stable tag: master
License: MIT
License URI: https://github.com/bitcart/bitcart-woocommerce/blob/master/LICENSE

Bitcart is a free and open-source cryptocurrency payment processor which allows you to receive cryptocurrency payments directly, with no fees, transaction cost or a middleman.

== Description ==

Bitcart is a free and open-source cryptocurrency payment processor which allows you to receive cryptocurrency payments directly, with no fees, transaction cost or a middleman.

Bitcart is a non-custodial invoicing system which eliminates the involvement of a third-party. Payments with Bitcart go directly to your wallet, which increases the privacy and security. Your private keys are never uploaded to the server. There is no address re-use since each invoice generates a new address deriving from your xpubkey.

You can run Bitcart as a self-hosted solution on your own server, or use a third-party host.

The self-hosted solution allows you not only to attach an unlimited number of stores and use the Lightning Network but also become the payment processor for others.


* Direct, peer-to-peer cryptocurrency payments
* No transaction fees (other than mining fees by crypto network itself)
* No processing fees
* No middleman
* No KYC
* User has complete control over private keys
* Enhanced privacy (no address re-use, no IP leaks to third parties)
* Enhanced security
* Self-hosted
* SegWit support
* Lightning Network support
* Altcoin support
* Attach unlimited stores, process payments for friends

== Installation ==

This plugin requires Woocommerce. Please make sure you have Woocommerce installed.

To integrate Bitcart into an existing WooCommerce store, follow the steps below.

### 1. Install Bitcart WooCommerce Plugin ###

### 2. Deploy Bitcart ###

To launch your Bitcart instance, you can self-host it, or use a third party host.

#### 2.1 Self-hosted Bitcart ####

There are various ways to [launch a self-hosted Bitcart](https://github.com/bitcart/bitcart-docker). If you do not have technical knowledge, use the [web-wizard method](https://launch.bitcart.ai) and follow the video below.

https://youtu.be/9BTYj-SVl4M

#### 2.2 Third-party host ####

Those who want to test Bitcart out, or are okay with the limitations of a third-party hosting (dependency and privacy, as well as lack of some features) can use one of the third-party hosts.

### 3. Connecting the store ###

Bitcart WooCommerce plugin is a bridge between your server (payment processor) and your e-commerce store. No matter if you\'re using a self-hosted or third-party solution from step 2, the connection process is identical.

Go to your store dashboard. WooCommerce > Settings > Payments. Click Bitcart.

1. In the field Bitcart URL, enter the full URL of your Bitcart API instance(in most cases api.somedomain.tld) (including the https) – https://api.mydomain.com
2. In the field Bitcart Store ID enter the id of your store(click on copy icon near your store in admin panel to get it), usually it is ID 1 for the first store on your instance.
3. In the field Bitcart Admin Panel URL enter the url(including the https) of your admin panel, like https://admin.mydomain.com
4. You have successfully connected your store! Congratulations!

###  4. Connecting your wallet ###

Don't forget to connect your wallet(s) to your store in admin panel, they will be used for checkout!

### 5. Testing the checkout ###

Making a small test-purchase from your own store, will give you a piece of mind. Always make sure that everything is set up correctly before going live.

== Screenshots ==

1. The Bitcart invoice. Your customers will see this at the checkout. They can pay from their wallet by scanning a QR or copy/pasting it manually into the wallet in any of the currencies supported by your installation.
2. Customizable plugin interface allows store owners to adjust everything according to their needs.
3. Customer will see the pay with Bitcoin button at the checkout. Text can be customized.
4. Example of sucessfuly paid invoice.
5. Example of the store you can launch with Bitcart ready solutions.

== Frequently Asked Questions ==

You'll find extensive documentation and answers to many of your questions on [docs.bitcart.ai](https://docs.bitcart.ai/).

== Changelog ==

## 1.0.0
Initial version

## 1.0.1
Fixes for latest Bitcart API updates

## 1.0.2

Fixes for Bitcart API updates

## 1.0.3

Compatibility with Bitcart v0.5.0.0

## 1.0.4

More fixes for invoice processing, clarify params

## 1.0.5

Fixes for stock level not increasing in case of expired invoices