# Simple Product Bundles for WooCommerce

A powerful yet lightweight WooCommerce extension for creating flexible product bundles with configurable quantities and volume discounts.

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0%2B-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0%2B-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

---

## ✨ Features

### 🎁 Custom Bundle Product Type
Create a new "Product Bundle" product type with full WooCommerce integration.

### 🔢 Flexible Quantity Controls
- **Minimum quantity** – Set required quantities (use 0 for optional items)
- **Maximum quantity** – Limit quantities (use 0 for unlimited)
- **Default quantity** – Pre-select quantities for customers

### 💰 Volume Discounts
Offer tiered pricing on individual bundled items:
- **Percentage discounts** – "Buy 5+, get 10% off each"
- **Fixed amount discounts** – "Buy 3+, get $2 off each"

### 🏷️ Bundle-wide Discounts
Apply an overall percentage discount to the entire bundle.

### ⚡ Real-time Price Updates
Customers see totals, savings, and discounts update instantly as they configure their bundle.

### 🌍 Translation Ready
Includes translations for:
- 🇩🇪 German (de_DE)
- 🇪🇸 Spanish (es_ES)
- 🇫🇷 French (fr_FR)
- 🇳🇱 Dutch (nl_NL)

---

## 📦 Installation

### From GitHub

1. Download or clone this repository
2. Upload the `simple-product-bundles` folder to `/wp-content/plugins/`
3. Activate the plugin through the **Plugins** menu in WordPress
4. Ensure WooCommerce is installed and activated

```bash
cd wp-content/plugins/
git clone https://github.com/your-username/simple-product-bundles.git
```

### Requirements

| Requirement | Version |
|-------------|---------|
| WordPress   | 5.0+    |
| WooCommerce | 5.0+    |
| PHP         | 7.4+    |

---

## 🚀 Usage

### Creating a Bundle

1. Go to **Products → Add New**
2. Select **Product Bundle** from the product type dropdown
3. Navigate to the **Bundle Items** tab
4. Click **Add Product** and search for products to include
5. Configure quantities for each item:
   - **Min Quantity** – Minimum required (0 = optional)
   - **Max Quantity** – Maximum allowed (0 = unlimited)
   - **Default Quantity** – Pre-selected amount
6. Optionally add **Volume Discounts** for each item
7. Set an overall **Bundle Discount** percentage
8. Publish your bundle!

### Volume Discount Tiers

Each bundled item can have multiple volume discount tiers:

| Quantity | Discount |
|----------|----------|
| Buy 3+   | 5% off   |
| Buy 5+   | 10% off  |
| Buy 10+  | 15% off  |

The highest applicable tier is automatically applied.

---

## 🏗️ Architecture

```
simple-product-bundles/
├── assets/
│   ├── admin.css          # Admin panel styles
│   ├── admin.js           # Admin panel scripts
│   ├── frontend.css       # Frontend bundle display
│   └── frontend.js        # Real-time price calculations
├── includes/
│   ├── class-simple-product-bundles-admin.php    # Admin functionality
│   ├── class-simple-product-bundles-ajax.php     # AJAX handlers
│   ├── class-simple-product-bundles-cart.php     # Cart & order handling
│   ├── class-simple-product-bundles-frontend.php # Frontend display
│   └── class-wc-product-bundle.php               # Bundle product type
├── languages/             # Translation files
├── simple-product-bundles.php  # Main plugin file
└── readme.txt             # WordPress readme
```

---

## 🔧 Compatibility

- ✅ **HPOS Compatible** – Works with WooCommerce High-Performance Order Storage
- ✅ **Block Themes** – Compatible with modern WordPress block themes
- ✅ **Variable Products** – Include product variations in bundles
- ✅ **Multi-language** – Translation-ready with WPML/Polylang support

---

## 🎨 Customization

### CSS Classes

The plugin uses semantic CSS classes for easy styling:

```css
/* Bundle container */
.bundle-items-wrapper { }
.bundle-items-list { }
.bundle-item { }

/* Quantity controls */
.bundle-qty-control { }
.bundle-qty-btn { }
.bundle-qty-input { }

/* Pricing */
.bundle-summary { }
.bundle-total { }
.bundle-volume-savings { }
```

### Filters & Actions

```php
// Modify bundle price calculation
add_filter('woocommerce_product_get_price', 'your_function', 10, 2);

// After bundle added to cart
add_action('woocommerce_add_to_cart', 'your_function', 10, 6);
```

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📄 License

This project is licensed under the GPL-2.0+ License - see the [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) file for details.

---

## 👥 Authors

**Aron & Sharon** – [aronandsharon.com](https://aronandsharon.com)

---

## 💬 Support

- 🐛 [Report a bug](../../issues)
- 💡 [Request a feature](../../issues)
- 📖 [Documentation](../../wiki)





