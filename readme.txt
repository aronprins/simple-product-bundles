=== Simple Product Bundles for WooCommerce ===
Contributors: aronandsharon
Tags: woocommerce, product bundles, bundle products, volume discounts, grouped products
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.1.0
WC requires at least: 5.0
WC tested up to: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create flexible product bundles with configurable quantities and volume discounts for your WooCommerce store.

== Description ==

**Simple Product Bundles** is a powerful yet easy-to-use WooCommerce extension that allows you to create customizable product bundles. Perfect for offering curated product sets, build-your-own kits, or quantity-based deals.

= Key Features =

* **Custom Bundle Product Type** – Creates a new "Product Bundle" type in WooCommerce with all the flexibility you need.
* **Flexible Quantity Controls** – Set minimum, maximum, and default quantities for each bundled item. Use min=0 for optional items or max=0 for unlimited quantities.
* **Volume Discounts** – Offer tiered discounts on individual items within bundles. Supports both percentage-based and fixed-amount discounts.
* **Bundle-wide Discounts** – Apply an overall percentage discount to the entire bundle price.
* **Real-time Price Calculation** – Customers see bundle totals, volume savings, and discounts update instantly as they configure their bundle.
* **Bundle Quantity Selector** – Optionally allow customers to order multiple bundles at once.
* **Drag & Drop Ordering** – Easily reorder bundled products in the admin panel.
* **Stock-aware** – Bundles automatically show as out of stock if any bundled item is unavailable.

= Perfect For =

* **Gift Sets** – Create curated gift bundles with optional add-ons
* **Build Your Own Kits** – Let customers customize product quantities within defined limits
* **Bulk Deals** – Offer volume-based pricing for individual items in bundles
* **Sample Packs** – Create starter kits with flexible configurations
* **Product Combinations** – Sell complementary products together at a discount

= How It Works =

1. Create a new product and select "Product Bundle" as the product type
2. Add products to your bundle using the intuitive search interface
3. Configure min/max/default quantities for each item
4. Optionally add volume discount tiers for individual items
5. Set an overall bundle discount percentage
6. Publish and start selling!

= Volume Discounts =

Volume discounts are applied per-item and support two discount types:

* **Percentage Off** – e.g., "Buy 5 or more, get 10% off each"
* **Fixed Amount Off** – e.g., "Buy 3 or more, get $2 off each"

Multiple tiers can be stacked, and the highest applicable tier is always used.

= Compatibility =

* WooCommerce 5.0+ (tested up to 8.0)
* WordPress 5.0+ (tested up to 6.4)
* High-Performance Order Storage (HPOS) compatible
* Translation-ready with included language files

== Installation ==

= Automatic Installation =

1. Go to **Plugins > Add New** in your WordPress admin
2. Search for "Simple Product Bundles"
3. Click **Install Now** and then **Activate**

= Manual Installation =

1. Download the plugin ZIP file
2. Go to **Plugins > Add New > Upload Plugin**
3. Upload the ZIP file and click **Install Now**
4. Activate the plugin

= Requirements =

* WordPress 5.0 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher

== Frequently Asked Questions ==

= Can I include variable products in bundles? =

Yes! You can add both simple and variable products to bundles. The product search supports variations.

= How are bundle prices calculated? =

Bundle prices are calculated dynamically based on:
1. Individual product prices × selected quantities
2. Volume discounts applied to qualifying items
3. Bundle-wide discount applied to the subtotal

= Can customers adjust quantities in the cart? =

Customers configure bundle quantities on the product page before adding to cart. The bundle is then added as a single line item with the configured quantities.

= Do bundled items reduce stock? =

Currently, bundle products use a simplified stock model. The bundle shows as in-stock only when all bundled items are in stock.

= Is this compatible with HPOS? =

Yes! Simple Product Bundles is fully compatible with WooCommerce's High-Performance Order Storage feature.

= Can I translate this plugin? =

Absolutely! The plugin is translation-ready and includes translation files for German, Spanish, French, and Dutch.

== Screenshots ==

1. Bundle configuration in the admin panel
2. Frontend bundle display with quantity controls
3. Volume discount tiers configuration
4. Cart display with bundle items
5. Order details showing bundle configuration

== Changelog ==

= 1.1.0 =
* New: Option to hide product images in bundle display
* Improved: Bundle now displays below short description on product page
* Updated: All translations for new strings

= 1.0.0 =
* Initial release
* Custom bundle product type
* Configurable min/max/default quantities
* Volume discounts with percentage and fixed amount support
* Bundle-wide percentage discount
* Real-time frontend price calculations
* HPOS compatibility
* Multi-language support (DE, ES, FR, NL)

== Upgrade Notice ==

= 1.1.0 =
New option to hide product images and improved bundle positioning on product pages.

= 1.0.0 =
Initial release of Simple Product Bundles for WooCommerce.

