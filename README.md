# 📚 Kitab Ghar — Book Shop & Stationery Management System (POS & ERP)

A comprehensive, all-in-one bookstore and stationery retail management system built with **Core PHP (Procedural)**, **MySQL (InnoDB)**, **Bootstrap 5**, **Chart.js**, and **AJAX/jQuery**.

---

## 🌟 Highlights & Key Features

### 1. 📊 Executive & Operational Dashboard
- **Live Financial KPIs**: Today's Sales, Purchases, Operating Expenses, Gross Profit, and Net Profit.
- **Ledger Balances**: Live Customer Receivables (Khata) and Supplier Payables.
- **Warehouse Valuation**: Real-time stock valuation by cost investment vs retail value and potential profit margin.
- **Stock Alert Bell**: Instant notifications for low-stock and out-of-stock items.
- **Analytics Charts**: 6-month historical monthly sales vs net profit trends using Chart.js.
- **Best Sellers & Activity**: Fast access to best-selling titles and recent POS transactions.

### 2. 📚 Book & Product Management
- Unified catalog for both **Textbooks/Novels** and **Stationery Items**.
- **Book Metadata**: ISBN, Title, Urdu Title, Author, Publisher, Edition, Publication Year, Subject, Class/Grade, Educational Board (Punjab Board, Federal, Oxford, Cambridge), and Language.
- **Stationery Tracking**: Category, units (Piece, Pack, Box, Dozen, Carton, Course Set).
- **Barcode Engine**: Instant Code128 / EAN-13 barcode sticker generation and printing powered by `JsBarcode`.
- **Location Tracking**: Physical storage allocation by Rack and Shelf.
- **CSV Import & Export**: One-click data export and bulk import.

### 3. 🛒 High-Speed POS Billing Terminal
- **Optimized for Speed**: Keyboard shortcuts (`F2` search, `F4` hold bill, `F7` view held, `F8` checkout, `F9` thermal print).
- **Instant Barcode Scanner**: Auto-focused barcode field with instant item addition on enter.
- **1-Click School Syllabus Bundle Loader**: Select an educational institution and class level (e.g. *Beaconhouse Class 5* or *Matric 9th Science*) to load all mandatory books and notebooks into the cart in a single click.
- **Customer Khata Selection**: Search walk-in cash customer or registered institutional client with live credit limit and balance warning.
- **Flexible Payments**: Cash, Bank Transfer, Mobile Wallet (JazzCash / EasyPaisa), or Khata (Credit Sale).
- **Dual-Mode Invoice Printing**:
  - **80mm Thermal Receipt**: Compact receipt with barcode, itemized table, and Urdu return policy.
  - **Professional A4 Invoice**: Formal commercial tax invoice with authorized stamp area and terms.
- **Hold & Resume Bills**: Suspend multiple carts during busy store hours and resume anytime.

### 4. 🏫 School, College & Academic Book Lists
- **School Profiles**: Manage partner educational institutes, contact persons, and academic sessions.
- **Class-Wise Syllabus Packages**: Define complete textbook and stationery requirements for each grade.
- **Bulk Shortage Checking**: Compare required student sets against live inventory stock to immediately detect shortages prior to school term openings.
- **Printable Syllabus Sheets**: Generate official booklists and price quotes for schools and parents.

### 5. 👥 Customer Management & Khata Ledger
- Complete customer profiles (Walk-in, Regular, Wholesale, School/Institution, Student Parent).
- **Debit / Credit Ledger**: Chronological transaction history of invoices, payments received, returns, and running balances.
- **Receive Payment Voucher**: Record customer payments directly into Cash in Hand or Bank accounts.
- **WhatsApp Reminder Integration**: Generates a pre-formatted bilingual (English & Urdu) payment reminder link sent directly to the customer's WhatsApp with their exact outstanding balance.

### 6. 🏢 Purchase & Supplier Management
- Manage publishers and distributors (Oxford University Press, PCTB, National Book Foundation, Dollar Stationery, etc.).
- **Inward Purchase Orders**: Add multiple items with purchase cost and quantity; automatically replenishes stock and updates supplier ledger.
- **Pay Supplier Voucher**: Pay publisher invoices from Cash or Bank accounts with running balance tracking.

### 7. 💰 Accounting, Daily Cashbook & Cash Drawer Closing
- **Multiple Accounts**: Counter Cash Drawer, Meezan Bank, HBL, and JazzCash/EasyPaisa.
- **Daily Cashbook**: Track all daily inflows (Sales, Capital, Payments) and outflows (Expenses, Supplier payments).
- **Expense Management**: Categorized vouchers for Shop Rent, Salaries, Electricity, Tea/Refreshment, Transport, and Maintenance.
- **Internal Funds Transfer**: Transfer money between drawer and bank accounts.
- **Daily Register Drawer Closing**: Automated calculation of expected closing cash against actual sales.

### 8. 🧾 Returns, Refunds & Exchanges
- **Sales Return Processing**: Look up invoices, select return quantities, and tag conditions (Good/Restockable vs Damaged).
- **Automated Reversal**: Restocks inventory for good books and refunds cash or credits customer khata.

### 9. 📈 Reports & Analytics
- **Profit & Loss Statement (P&L)**: Gross Revenue - Customer Discounts = Net Sales; Net Sales - Cost of Goods Sold (COGS) = Gross Profit; Gross Profit - Operating Expenses = Net Profit.
- **Product-Wise Sales & Margin Report**: Best selling books with profitability percentages.
- **Category Sales Breakdown**: Books vs stationery revenue comparisons.
- **Customer Receivables & Supplier Payables Summary**.

### 10. 🌐 Urdu Localization & Role-Based Access Control
- **Dual-Language Switcher**: Toggle between English (LTR) and **اردو (RTL)** layout with custom Urdu typography.
- **Role Permissions**: Admin (full control), Manager (purchases & stock), Cashier (POS & billing), and Accountant (ledgers & reports).

---

## 🔑 Default Credentials

| Role | Username | Password | Access Level |
|---|---|---|---|
| **Administrator** | `admin` | `admin123` | Complete Full System Access |
| **Cashier** | `cashier` | `cashier123` | POS Billing, Invoices & Customers |
| **Accountant** | `accountant` | `acc123` | Ledgers, Cashbook, Expenses & Reports |

---

## 🚀 Installation & Setup

1. **Access in Browser**:
   ```
   http://localhost/Books/
   ```
2. **First Time Setup**:
   If the database is fresh, open:
   ```
   http://localhost/Books/install.php
   ```
   and click **"Initialize Database Now"**. It creates all 21 tables and loads realistic seed data (Oxford syllabus books, Punjab Board textbooks, Urdu literature, stationery items, schools, and sample transactions).

---

## 🗄️ Database Architecture (MySQL)

- `products`: Product catalog, ISBN, author, publisher, prices, barcode, rack/shelf.
- `categories`: Book and stationery categories.
- `schools` & `school_classes` & `school_class_items`: Academic syllabus packages and bundles.
- `customers` & `customer_ledgers`: Customer khata, balances, credit limits.
- `suppliers` & `supplier_ledgers`: Publisher accounts and payables.
- `accounts`: Cash and bank accounts.
- `cash_transactions`: Daily cashbook, expenses, and income.
- `sales` & `sale_items`: Invoices, line items, and profit tracking.
- `purchases` & `purchase_items`: Inward stock receiving.
- `stock_movements`: Stock audit trail for all additions and subtractions.
- `returns` & `return_items`: Sales and purchase returns.
- `settings`: Global shop configuration, currency, and receipt footers.
- `users`: User authentication and role permissions.
