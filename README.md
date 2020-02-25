# 💸 WooCommerce Price Changer
This is a WooCommerce plugin for handling temporary prices.

## Installation

First of all clone the project or download the zip file in the repository.

Then install the file *wc-price-changer.php* into the plugin section of your WordPress site and activate it.

## Usage

In the WP-Admin section of your WordPress site navigate to *WooCommerce* > *WC Price Changer*.

The plugin dashboard contains a table with all the products in WooCommerce's database: the table let admins to do bulk actions with one or more products selected.


The main actions are the *unit price change* and the *percentage price change*.
The first one is for increasing or decreasing products prices by an unit value.
The last one increases or decreases products prices by a percentage value.

The price decrease will set the *sale price* for every selected product from its own *regular price*.
The price increase will set the *price* field for selected products from *regular price*.

It's possible to periodize price changing by selecting datetime from the action setup.
Without selecting datetime the price changing will directly affect your WooCommerce database.
