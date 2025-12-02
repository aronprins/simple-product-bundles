# 🧾 Simple Product Bundles v1.2.0

**Per-Product Tax Calculation** – Each bundled product now uses its own tax class for accurate VAT/tax calculations.

---

## ✨ What's New

### 💶 Per-Product Tax Class Support
- Each bundled product's **individual tax class** is now respected
- No more "best match" tax class for the entire bundle
- Accurate tax calculations for mixed-rate bundles (e.g., 21% + 9% BTW)
- Uses WooCommerce's precision system for cent-accurate calculations

### 📊 Collapsible Tax Breakdown
- **Combined tax total** displayed as a single line (e.g., "BTW €166,95")
- Click to **expand** and see per-product breakdown:
  - Product name
  - Subtotal amount
  - Tax rate percentage
  - Tax amount
- Works with both **Classic Cart** and **WooCommerce Blocks Cart**
- Hides default WooCommerce tax rows when breakdown is active

### 🎨 Improved Cart Display
- Seamless integration with WooCommerce Blocks cart via JavaScript
- Classic cart uses proper table row structure
- Smooth slide animation for expand/collapse
- Accessible with proper ARIA attributes

---

## 🔧 How It Works

**Example Bundle:**
- Product A: €495 @ 21% tax class → €103.95 tax
- Product B: €700 @ 9% tax class → €63.00 tax
- **Total Tax: €166.95** (correctly calculated per product)

**In Cart:**
```
BTW                    €166,95 ▼
                       ┌─────────────────────────────┐
                       │ Product A                   │
                       │ €495,00 @ 21%    €103,95    │
                       ├─────────────────────────────┤
                       │ Product B                   │
                       │ €700,00 @ 9%     €63,00     │
                       └─────────────────────────────┘
```

---

## 🐛 Bug Fixes
- Fixed tax calculation using WooCommerce precision (cents) to avoid rounding errors

---

**Full Changelog**: https://github.com/aronprins/simple-product-bundles/compare/v1.1.2...v1.2.0

---
---

# 🚀 Simple Product Bundles v1.1.0

**Display Improvements** – Better control over bundle appearance on product pages.

---

## ✨ What's New

### 🖼️ Hide Product Images Option
- New toggle in bundle configuration to **hide product thumbnails**
- Perfect for text-focused bundle displays or compact layouts
- Layout automatically adjusts when images are hidden

### 📍 Improved Bundle Position
- Bundle display now appears **below the short description**
- More natural placement in the product page flow
- Follows standard WooCommerce add-to-cart positioning

### 🌍 Updated Translations
- All new strings translated for:
  - 🇩🇪 German (de_DE)
  - 🇪🇸 Spanish (es_ES)
  - 🇫🇷 French (fr_FR)
  - 🇳🇱 Dutch (nl_NL)

---

## 🔧 How to Use

1. Edit your bundle product
2. Go to the **Bundle Items** tab
3. Check **"Hide product images"** to remove thumbnails
4. Save and view your streamlined bundle display

---

**Full Changelog**: https://github.com/aronprins/simple-product-bundles/compare/v1.0.0...v1.1.0

---
---

# 🎉 Simple Product Bundles v1.0.0

**Initial Release** – Create flexible product bundles with configurable quantities and volume discounts for WooCommerce.

---

## ✨ What's New

### 🎁 Bundle Product Type
- New **Product Bundle** product type fully integrated with WooCommerce
- Bundles appear in product listings, search, and archives
- Automatic price range display (min–max) based on quantity configurations

### 🔢 Configurable Quantities
- **Minimum quantity** per item (set to 0 for optional items)
- **Maximum quantity** per item (set to 0 for unlimited)
- **Default quantity** pre-selected for customers
- Intuitive +/− quantity controls on the frontend

### 💰 Volume Discounts
- Add unlimited discount tiers per bundled item
- **Percentage discounts** – "Buy 5+, get 10% off"
- **Fixed amount discounts** – "Buy 3+, get $2 off each"
- Visual discount badges displayed to customers
- Automatic tier calculation (highest applicable tier wins)

### 🏷️ Bundle-wide Discount
- Apply an overall percentage discount to the entire bundle
- Stacks with individual item volume discounts
- Clear savings breakdown in the price summary

### ⚡ Real-time Frontend Experience
- Instant price updates as customers adjust quantities
- Live subtotal, volume savings, and total calculations
- Responsive quantity controls with validation
- Quantity hints showing allowed ranges

### 🛒 Cart & Order Integration
- Bundle configuration displayed in cart
- Volume discount details shown per item
- Full order meta for bundle contents
- Proper line item display in order emails

### 🔒 Security
- Nonce verification on all forms
- Input sanitization throughout
- Capability checks for admin operations

### 🌍 Internationalization
- Fully translation-ready with text domain
- Included translations:
  - 🇩🇪 German (de_DE)
  - 🇪🇸 Spanish (es_ES)
  - 🇫🇷 French (fr_FR)
  - 🇳🇱 Dutch (nl_NL)

### ✅ Compatibility
- **WordPress 5.0+** (tested up to 6.4)
- **WooCommerce 5.0+** (tested up to 8.0)
- **PHP 7.4+**
- **HPOS Compatible** – Works with High-Performance Order Storage
- Supports simple and variable products in bundles

---

## 📦 Installation

1. Download the ZIP file from this release
2. Go to **Plugins → Add New → Upload Plugin** in WordPress
3. Upload the ZIP and click **Install Now**
4. Activate the plugin
5. Create your first bundle under **Products → Add New**

---

## 🔧 Requirements

| Requirement | Minimum Version |
|-------------|-----------------|
| WordPress   | 5.0             |
| WooCommerce | 5.0             |
| PHP         | 7.4             |

---

## 📝 Usage

1. Create a new product and select **Product Bundle** as the type
2. Go to the **Bundle Items** tab
3. Add products and configure quantities
4. Optionally add volume discount tiers
5. Set an overall bundle discount
6. Publish and start selling!

---

## 🙏 Acknowledgments

Thank you for using Simple Product Bundles! We'd love to hear your feedback.

---

**Full Changelog**: https://github.com/your-username/simple-product-bundles/commits/v1.0.0

