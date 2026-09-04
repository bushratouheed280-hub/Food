# 🍔 Fast Food Website

A full-stack food ordering website built with PHP, MySQL, HTML, CSS, JavaScript and Bootstrap.

The project includes a public food website, customer panel and admin panel with authentication, order management and database integration.

## ✨ Features

### 👤 Customer
- Customer registration and login
- Secure session handling
- Customer dashboard
- Profile management
- Browse food menu
- Add items to cart
- Checkout
- Cash on Delivery
- View order history
- View order details
- Cancel eligible orders
- Order status tracking
- Logout

### 🔐 Admin
- Secure admin login
- Admin dashboard
- Customer management
- Add, edit and delete customers
- Order management
- View order details
- Update order status
- Menu management
- Reports section
- Admin profile
- Secure admin logout

### 🛒 Ordering System
- Dynamic food menu
- Shopping cart
- Checkout system
- Order creation
- Order items management
- Delivery information
- Payment method
- Order status management
- Customer order history

## 🛠️ Technologies Used

- HTML5
- CSS3
- JavaScript
- Bootstrap 5
- PHP
- MySQL
- Font Awesome
- XAMPP

## 🗄️ Database

The project uses MySQL/MariaDB.

Main database tables include:

- `admins`
- `customers`
- `menu_items`
- `orders`
- `order_items`

Database files are available inside the `database` folder.

## 🔒 Security

The project includes:

- Password hashing and verification
- Prepared statements
- Session-based authentication
- Admin/customer session separation
- Access protection for authenticated pages
- Customer order ownership protection
- Safe output escaping
- Database relationship and integrity protection

## 🚀 How to Run Locally

### 1. Install XAMPP

Install XAMPP with Apache and MySQL.

### 2. Start Services

Open XAMPP Control Panel and start:

- Apache
- MySQL

### 3. Clone the Repository

Clone or download this repository into the XAMPP `htdocs` folder.

Example:

```text
C:\xampp\htdocs\FOOD WEBSITE
