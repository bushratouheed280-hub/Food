<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/config/security.php';
    start_secure_session('fastfood_customer');
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/security.php';

$cartQuantity = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $cartItem) {
        if (isset($cartItem['quantity']) && is_numeric($cartItem['quantity'])) {
            $cartQuantity += (int) $cartItem['quantity'];
        }
    }
}

$menuItems = [];

try {
    $connection = get_db_connection();
    $available = 1;
    $statement = $connection->prepare(
        'SELECT menu_id, name, description, category, price, image_url, rating, popularity, discount_percent, is_available
         FROM menu_items
         WHERE is_available = ?
         ORDER BY menu_id ASC'
    );

    if ($statement) {
        $statement->bind_param('i', $available);
        if ($statement->execute()) {
            $result = $statement->get_result();
            if ($result) {
                $menuItems = $result->fetch_all(MYSQLI_ASSOC);
            }
        }
        $statement->close();
    }

    $connection->close();
} catch (Throwable $exception) {
    $menuItems = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fast Food</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css"
        integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="style.css">
</head>

<body>

    <!-- NAVBAR -->

    <section id="home">
        <header class="site-navbar" id="siteNavbar">
            <div class="container-fluid nav-shell">
                <a class="logo" href="#home" aria-label="Fast Food home">
                    <img src="https://static.vecteezy.com/system/resources/thumbnails/019/607/567/small/fast-food-vector-clipart-design-graphic-clipart-design-free-png.png"
                        alt="Fast Food logo">
                </a>

                <button class="menu-toggle" type="button" aria-label="Toggle navigation" aria-expanded="false">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>

                <div class="nav-center">
                    <ul class="nav-links" id="navLinks">
                        <li><a class="nav-link active" href="#home">Home</a></li>
                        <li><a class="nav-link" href="#About">About</a></li>
                        <li><a class="nav-link" href="#Menu">Menu</a></li>
                        <li><a class="nav-link" href="#Gallery">Gallery</a></li>
                        <li><a class="nav-link" href="#Review">Reviews</a></li>
                        <li><a class="nav-link" href="#Order">Order</a></li>
                        <li class="mobile-login-item"><a class="nav-link" href="auth/index.php">Login</a></li>
                    </ul>
                </div>

                <div class="nav-actions">
                    <div class="nav-search">
                        <button class="nav-icon search-toggle" type="button" aria-label="Search menu">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                        <div class="search-panel" id="searchPanel">
                            <div class="search-input-wrap">
                                <i class="fa-solid fa-magnifying-glass"></i>
                                <input type="text" id="menuSearch" placeholder="Search menu items...">
                            </div>
                            <div class="search-results" id="searchResults"></div>
                        </div>
                    </div>
                    <a class="nav-icon cart-icon" href="cart.php" aria-label="Cart">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span class="cart-badge"><?= htmlspecialchars((string) $cartQuantity, ENT_QUOTES, 'UTF-8'); ?></span>
                    </a>
                    <a class="login-btn" href="auth/index.php" id="openAuthModal"><i class="fa-regular fa-user"></i> Login</a>
                </div>
            </div>
        </header>
        <div class="mobile-menu-backdrop" aria-hidden="true"></div>

        <!-- MAIN -->
        <div class="main hero-shell">
            <div class="hero-content">
                <span class="hero-badge"><i class="fa-solid fa-fire"></i> Best Fast Food Restaurant</span>
                <h1>Fresh, Hot & Tasty Burgers Every Day</h1>
                <p>
                    Fresh ingredients, fast delivery, and irresistible flavors crafted for every craving.
                    Enjoy premium meals made with care, served quickly, and delivered to your doorstep.
                </p>

                <div class="hero-actions">
                    <a href="#Order" class="btn hero-btn primary-btn"><i class="fa-solid fa-burger"></i> Order Now</a>
                    <a href="#Menu" class="btn hero-btn secondary-btn"><i class="fa-solid fa-utensils"></i> Explore Menu</a>
                </div>

                <div class="hero-features">
                    <div class="feature-pill"><i class="fa-solid fa-leaf"></i> Fresh Ingredients</div>
                    <div class="feature-pill"><i class="fa-solid fa-truck-fast"></i> Fast Delivery</div>
                    <div class="feature-pill"><i class="fa-solid fa-headset"></i> 24/7 Support</div>
                </div>

                <div class="hero-rating">
                    <div class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></div>
                    <div>
                        <strong>4.9 Rating</strong>
                        <span>10,000+ Happy Customers</span>
                    </div>
                </div>
            </div>

            <div class="hero-visual">
                <div class="hero-glow"></div>
                <div class="hero-image-card">
                    <img src="https://png.pngtree.com/png-vector/20240710/ourmid/pngtree-burger-with-floating-ingredient-png-image_13054386.png" alt="Premium burger hero image">
                    <div class="hero-image-tag"><i class="fa-solid fa-bolt"></i> Signature Burger</div>
                </div>
                <div class="delivery-card">
                    <i class="fa-solid fa-truck-fast"></i>
                    <div>
                        <strong>Fast Delivery</strong>
                        <span>30 Minutes</span>
                    </div>
                </div>
            </div>
        </div>

    </section>

    <!-- ABOUT -->

    <section class="about" id="About" aria-label="About us">
        <div class="container about_main">
            <div class="row align-items-center gx-5">
                <!-- Image (Left on desktop) -->
                <div class="col-lg-6">
                    <figure class="about-image-card shadow-lg">
                        <div class="about-image-badges">
                            <span class="badge badge-gold"><i class="fa-solid fa-star"></i> Premium Quality</span>
                            <span class="badge badge-ghost"><i class="fa-solid fa-burger"></i> Since 2005</span>
                        </div>
                        <img src="https://freepngimg.com/save/138933-food-plate-breakfast-hd-image-free/1400x1098" alt="Gourmet burger plated with sides" class="img-fluid">
                    </figure>
                </div>

                <!-- Content (Right on desktop) -->
                <div class="col-lg-6">
                    <div class="about-text">
                        <span class="section-label">ABOUT US</span>
                        <h2 class="about-title">We Serve the Best Burgers in Town</h2>
                        <p class="about-description">
                            We combine fresh, locally sourced ingredients with masterful techniques from our certified chefs to deliver premium taste every time. Fast delivery, warm service, and consistent quality ensure customer satisfaction with every order.
                        </p>

                        <!-- Statistics -->
                        <div class="about-stats row gy-3 mb-4">
                            <div class="col-6 col-md-3">
                                <div class="stat-card text-center">
                                    <div class="stat-icon"><i class="fa-solid fa-utensils"></i></div>
                                    <div class="stat-number">100+</div>
                                    <div class="stat-label">Menu Items</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card text-center">
                                    <div class="stat-icon"><i class="fa-solid fa-users"></i></div>
                                    <div class="stat-number">5000+</div>
                                    <div class="stat-label">Happy Customers</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card text-center">
                                    <div class="stat-icon"><i class="fa-solid fa-user-chef"></i></div>
                                    <div class="stat-number">15+</div>
                                    <div class="stat-label">Professional Chefs</div>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="stat-card text-center">
                                    <div class="stat-icon"><i class="fa-solid fa-award"></i></div>
                                    <div class="stat-number">20+</div>
                                    <div class="stat-label">Years Experience</div>
                                </div>
                            </div>
                        </div>

                        <!-- Features -->
                        <div class="about-features row g-2 mb-4">
                            <div class="col-6 col-sm-4"><div class="feature-item"><i class="fa-solid fa-check"></i> Fresh Ingredients</div></div>
                            <div class="col-6 col-sm-4"><div class="feature-item"><i class="fa-solid fa-check"></i> Certified Chefs</div></div>
                            <div class="col-6 col-sm-4"><div class="feature-item"><i class="fa-solid fa-check"></i> Fast Delivery</div></div>
                            <div class="col-6 col-sm-4"><div class="feature-item"><i class="fa-solid fa-check"></i> Hygienic Kitchen</div></div>
                            <div class="col-6 col-sm-4"><div class="feature-item"><i class="fa-solid fa-check"></i> Best Quality Food</div></div>
                            <div class="col-6 col-sm-4"><div class="feature-item"><i class="fa-solid fa-check"></i> Affordable Prices</div></div>
                        </div>

                        <!-- Actions -->
                        <div class="about-actions d-flex flex-wrap gap-3">
                            <a href="#Menu" class="btn about-btn about-btn-primary">Explore Menu</a>
                            <a href="#Order" class="btn about-btn about-btn-secondary">Learn More</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- MENU -->

    <div class="menu" id="Menu">
        <div class="menu-header">
            <span class="menu-subtitle">OUR MENU</span>
            <h1>Choose Your Favorite Meal</h1>
            <p>Freshly crafted favorites made to satisfy every craving with speed, style, and premium flavor.</p>
        </div>

        <div class="menu-toolbar">
            <div class="menu-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="menuSearchBar" placeholder="Search delicious food...">
            </div>

            <div class="menu-controls">
                <div class="menu-filters" role="tablist" aria-label="Menu categories">
                    <button class="filter-btn active" type="button" data-filter="all">All</button>
                    <button class="filter-btn" type="button" data-filter="burger">Burger</button>
                    <button class="filter-btn" type="button" data-filter="pizza">Pizza</button>
                    <button class="filter-btn" type="button" data-filter="fries">Fries</button>
                    <button class="filter-btn" type="button" data-filter="drinks">Drinks</button>
                    <button class="filter-btn" type="button" data-filter="dessert">Dessert</button>
                </div>

                <div class="menu-sort">
                    <label for="sortSelect">Sort By</label>
                    <select id="sortSelect">
                        <option value="default">Default</option>
                        <option value="price-asc">Price Low to High</option>
                        <option value="price-desc">Price High to Low</option>
                        <option value="popularity">Popularity</option>
                        <option value="newest">Newest</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="menu_card">
            <?php if (empty($menuItems)): ?>
                <div class="menu-empty">No menu items are available right now. Please check back later.</div>
            <?php else: ?>
                <?php foreach ($menuItems as $item): ?>
                    <?php
                    $menuId = (int) ($item['menu_id'] ?? 0);
                    $menuName = (string) ($item['name'] ?? 'Menu Item');
                    $menuDescription = (string) ($item['description'] ?? 'Freshly prepared meal for your cravings.');
                    $menuCategory = strtolower((string) ($item['category'] ?? 'burger'));
                    $menuPrice = (float) ($item['price'] ?? 0.0);
                    $menuPopularity = (int) ($item['popularity'] ?? 0);
                    $discountPercent = (float) ($item['discount_percent'] ?? 0.0);
                    $menuImage = (string) ($item['image_url'] ?? '');
                    $menuRating = (float) ($item['rating'] ?? 5.0);
                    $stars = str_repeat('★', min(5, max(1, (int) round($menuRating))));
                    ?>
                    <div class="card"
                        data-menu-id="<?= htmlspecialchars((string) $menuId, ENT_QUOTES, 'UTF-8'); ?>"
                        data-category="<?= htmlspecialchars($menuCategory, ENT_QUOTES, 'UTF-8'); ?>"
                        data-price="<?= htmlspecialchars((string) $menuPrice, ENT_QUOTES, 'UTF-8'); ?>"
                        data-popularity="<?= htmlspecialchars((string) $menuPopularity, ENT_QUOTES, 'UTF-8'); ?>"
                        data-name="<?= htmlspecialchars($menuName, ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="menu-card-badges">
                            <span class="price-badge">$<?= number_format($menuPrice, 2); ?></span>
                            <?php if ($discountPercent > 0): ?>
                                <span class="discount-badge"><?= (int) round($discountPercent); ?>% OFF</span>
                            <?php endif; ?>
                            <button class="fav-btn" type="button" aria-label="Add to favorites"><i class="fa-regular fa-heart"></i></button>
                        </div>

                        <?php if ($menuImage !== ''): ?>
                            <img src="<?= htmlspecialchars($menuImage, ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($menuName, ENT_QUOTES, 'UTF-8'); ?>" class="image">
                        <?php else: ?>
                            <img src="https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=800&auto=format&fit=crop&q=60" alt="<?= htmlspecialchars($menuName, ENT_QUOTES, 'UTF-8'); ?>" class="image">
                        <?php endif; ?>

                        <h3><?= htmlspecialchars($menuName, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <p><?= htmlspecialchars($menuDescription, ENT_QUOTES, 'UTF-8'); ?></p>

                        <div class="menu-card-meta">
                            <span class="menu-stars"><?= htmlspecialchars($stars, ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="menu-time"><i class="fa-solid fa-clock"></i> 20 min</span>
                        </div>

                        <button type="button" class="btn btn-warning menu-order-btn" data-menu-id="<?= htmlspecialchars((string) $menuId, ENT_QUOTES, 'UTF-8'); ?>" data-name="<?= htmlspecialchars($menuName, ENT_QUOTES, 'UTF-8'); ?>" data-price="<?= htmlspecialchars((string) $menuPrice, ENT_QUOTES, 'UTF-8'); ?>" data-image-url="<?= htmlspecialchars($menuImage, ENT_QUOTES, 'UTF-8'); ?>">
                            <i class="fa-solid fa-cart-shopping"></i> Add to Cart
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- GALLERY -->

    <div class="gallery" id="Gallery">
        <h1>Our<span>Gallery</span></h1>

        <div class="gallery_image_box">
            <div class="gallery_image">
                <img src="https://www.watermelon.org/wp-content/uploads/2023/02/Sandwich_2023-1000x1000.jpg" alt="">
                <h3>Food</h3>
                <p>
                    Lorem, ipsum dolor sit amet consectetur adipisicing elit. Quaerat quasi veritatis earum voluptas
                    debitis nisi sint impedit! Minima quasi maiores pariatur beatae, dicta quos quaerat doloribus
                    molestias aut quidem vel?
                </p>
                <a href="#Order" class="btn btn-warning">Order Now</a>
            </div>

            <div class="gallery_image">
                <img src="https://www.biggerbolderbaking.com/wp-content/uploads/2021/07/Baked-Doughnuts-thumbnail-scaled.jpg"
                    alt="">
                <h3>Food</h3>
                <p>
                    Lorem, ipsum dolor sit amet consectetur adipisicing elit. Quaerat quasi veritatis earum voluptas
                    debitis nisi sint impedit! Minima quasi maiores pariatur beatae, dicta quos quaerat doloribus
                    molestias aut quidem vel?
                </p>
              <a href="#Order" class="btn btn-warning">Order Now</a>
            </div>

            <div class="gallery_image">
                <img src="https://img.freepik.com/free-photo/spaghetti-with-bolognese-sauce-wooden-tablexa_123827-22962.jpg?semt=ais_hybrid&w=740&q=80"
                    alt="">
                <h3>Food</h3>
                <p>
                    Lorem, ipsum dolor sit amet consectetur adipisicing elit. Quaerat quasi veritatis earum voluptas
                    debitis nisi sint impedit! Minima quasi maiores pariatur beatae, dicta quos quaerat doloribus
                    molestias aut quidem vel?
                </p>
                <a href="#Order" class="btn btn-warning">Order Now</a>
            </div>

            <div class="gallery_image">
                <img src=https://i.ytimg.com/vi/IBlIMdCyVgw/hq720.jpg?sqp=-oaymwEhCK4FEIIDSFryq4qpAxMIARUAAAAAGAElAADIQj0AgKJD&rs=AOn4CLAdPgE8zgq6MmyB1fN1xEYffhybtw"
                    alt="">
                <h3>Food</h3>
                <p>
                    Lorem, ipsum dolor sit amet consectetur adipisicing elit. Quaerat quasi veritatis earum voluptas
                    debitis nisi sint impedit! Minima quasi maiores pariatur beatae, dicta quos quaerat doloribus
                    molestias aut quidem vel?
                </p>
                <a href="#Order" class="btn btn-warning">Order Now</a>
            </div>

            <div class="gallery_image">
                <img src="https://www.allrecipes.com/thmb/i9KCEbxUGQ1Sa4F7Gts7SGBOpoM=/1500x0/filters:no_upscale():max_bytes(150000):strip_icc()/157877-vanilla-cupcakes-ddmfs-4X3-0397-59653731be1d4769969698e427d7f5bc.jpg"
                    alt="">
                <h3>Food</h3>
                <p>
                    Lorem, ipsum dolor sit amet consectetur adipisicing elit. Quaerat quasi veritatis earum voluptas
                    debitis nisi sint impedit! Minima quasi maiores pariatur beatae, dicta quos quaerat doloribus
                    molestias aut quidem vel?
                </p>
                <a href="#Order" class="btn btn-warning">Order Now</a>
            </div>

            <div class="gallery_image">
                <img src="https://www.budgetbytes.com/wp-content/uploads/2024/07/Grilled-Vegetables-Overhead-500x500.jpg"
                    alt="">
                <h3>Food</h3>
                <p>
                    Lorem, ipsum dolor sit amet consectetur adipisicing elit. Quaerat quasi veritatis earum voluptas
                    debitis nisi sint impedit! Minima quasi maiores pariatur beatae, dicta quos quaerat doloribus
                    molestias aut quidem vel?
                </p>
               <a href="#Order" class="btn btn-warning">Order Now</a>
            </div>
        </div>


    </div>

    <!-- REVIEWS -->

    <div class="review" id="Review">
        <h1>Customer<span>Review</span></h1>
        <div class="review_card">
            <div class="card">
                <img src="https://img.freepik.com/free-photo/front-view-cheerful-man-holding-phone-looking-straight_176420-11748.jpg?semt=ais_hybrid&w=740&q=80"
                    alt="" class="image">
                <h3>John</h3>
                <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Esse, consequuntur dolorem repellendus,
                    doloribus pariatur beatae officiis eaque nesciunt eos eveniet accusamus quia dolore, mollitia ipsum
                    culpa. Illo dolor tempora assumenda?</p>

                <div class="icon">
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>


                </div>
            </div>

            <div class="card">
                <img src="https://img.freepik.com/free-photo/blissful-caucasian-girl-wears-tweed-jacket-reading-phone-message-city-background_197531-6770.jpg?semt=ais_hybrid&w=740&q=80"
                    alt="" class="image">
                <h3>John</h3>
                <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Esse, consequuntur dolorem repellendus,
                    doloribus pariatur beatae officiis eaque nesciunt eos eveniet accusamus quia dolore, mollitia ipsum
                    culpa. Illo dolor tempora assumenda?</p>

                <div class="icon">
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>


                </div>
            </div>

            <div class="card">
                <img src="https://img.freepik.com/free-photo/young-woman-happy-red-french-beret_1303-31374.jpg?semt=ais_hybrid&w=740&q=80"
                    alt="" class="image">
                <h3>John</h3>
                <p>Lorem ipsum dolor sit amet consectetur adipisicing elit. Esse, consequuntur dolorem repellendus,
                    doloribus pariatur beatae officiis eaque nesciunt eos eveniet accusamus quia dolore, mollitia ipsum
                    culpa. Illo dolor tempora assumenda?</p>

                <div class="icon">
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>
                    <i class="fa-solid fa-star"></i>


                </div>
            </div>
        </div>



    </div>

    <div class="order" id="Order">

        <h1><span>Order</span>Now</h1>

        <div class="order_main">
            <div class="order_image">
                <img src="https://png.pngtree.com/png-vector/20240313/ourmid/pngtree-order-food-call-center-png-image_11938364.png"
                    alt="">

            </div>
            <form>
                <div class="row">
                <div class="col-md-12 ">
                    <div class="input">
                        <label class="form-label"id="name">Name</label>
                        <input class="form-control mb-2" id="name" type="text" placeholder="Enter your name">
                    </div>
                </div>
                </div>

                 <div class="row">
                <div class="col-md-12">
                    <div class="input">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control mb-2" id="email" type="email" placeholder="Enter your Email">
                    </div>
                </div>
                </div>

                 <div class="row">
                <div class="col-md-12">
                    <div class="input">
                        <label class="form-label"for="number">Number</label>
                        <input class="form-control mb-2" id="number" type="number" placeholder="Enter your number">
                    </div>
                </div>
                </div>

                 <div class="row">
                <div class="col-md-12">
                    <div class="input">
                        <label class="form-label" for="how much">How Much</label>
                        <input class="form-control mb-2" id="how much" type="number" placeholder="How many order">
                    </div>
                </div>
                </div>

                 <div class="row">
                <div class="col-md-12">
                    <div class="input">
                        <label class="form-label"for="order">Your Order</label>
                        <input class="form-control mb-2" id="order" type="text" placeholder="Food Name">
                    </div>
                </div>
                </div>

                 <div class="row">
                <div class="col-md-12">
                    <div class="input">
                        <label class="form-label"for="adress">Adress</label>
                        <input class="form-control mb-2" id="adress" placeholder="Your Adress">
                    </div>
                </div>
                </div>

<a href="#Order" class="btn btn-warning">Order Now</a>
            </form>

        </div>

    </div>













    <div class="auth-modal" id="authModal" aria-hidden="true">
        <div class="auth-modal-backdrop" data-close-auth="true"></div>
        <div class="auth-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="authModalTitle">
            <button class="auth-close" type="button" id="closeAuthModal" aria-label="Close login panel">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div class="auth-tabs">
                <button class="auth-tab" type="button" data-auth-mode="register">Register</button>
                <button class="auth-tab active" type="button" data-auth-mode="login">Login</button>
            </div>

            <h3 id="authModalTitle">Login to Your Account</h3>
            <p class="auth-subtitle">Welcome back! Sign in to continue your order.</p>

            <form class="auth-form" data-auth-form="register">
                <label>
                    <span>Full Name</span>
                    <input type="text" placeholder="Enter your full name" required>
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" placeholder="Enter your email" required>
                </label>
                <label>
                    <span>Password</span>
                    <input type="password" placeholder="Create a password" required>
                </label>
                <button class="auth-submit" type="submit">Register</button>
                <p class="auth-switch-text">Already have an account? <button type="button" class="auth-switch-btn" data-switch-auth="login">Login</button></p>
            </form>

            <form class="auth-form active" data-auth-form="login">
                <label>
                    <span>Email</span>
                    <input type="email" placeholder="Enter your email" required>
                </label>
                <label>
                    <span>Password</span>
                    <input type="password" placeholder="Enter your password" required>
                </label>
                <button class="auth-submit" type="submit">Login</button>
                <p class="auth-switch-text">New here? <button type="button" class="auth-switch-btn" data-switch-auth="register">Register</button></p>
            </form>
        </div>
    </div>

    <script>
        const navbar = document.getElementById('siteNavbar');
        const toggleButton = document.querySelector('.menu-toggle');
        const navLinks = document.getElementById('navLinks');
        const mobileMenuBackdrop = document.querySelector('.mobile-menu-backdrop');
        const navItems = document.querySelectorAll('.nav-link');
        const sections = document.querySelectorAll('#home, #About, #Menu, #Gallery, #Review, #Order');
        const searchToggle = document.querySelector('.search-toggle');
        const searchPanel = document.getElementById('searchPanel');
        const searchInput = document.getElementById('menuSearch');
        const searchResults = document.getElementById('searchResults');
        const menuCards = Array.from(document.querySelectorAll('.menu .card'));
        const setActiveLink = () => {
            let currentId = 'home';
            const offset = window.scrollY + 140;

            sections.forEach((section) => {
                if (section.offsetTop <= offset) {
                    currentId = section.id;
                }
            });

            navItems.forEach((link) => {
                const isActive = link.getAttribute('href') === `#${currentId}`;
                link.classList.toggle('active', isActive);
            });
        };

        const menuState = {
            query: '',
            category: 'all',
            sort: 'default'
        };

        const setupMenuCardUI = () => {
            menuCards.forEach((card, index) => {
                const title = card.querySelector('h3')?.textContent.trim() || `Menu Item ${index + 1}`;
                const category = card.dataset.category || 'burger';
                const price = Number(card.dataset.price || 0);
                const popularity = Number(card.dataset.popularity || 0);
                const discount = Number(card.dataset.price || 0) > 0 && card.dataset.name && ['Classic Burger', 'Spicy Chicken Grill', 'Italian Pizza Slice', 'Signature Platter'].includes(card.dataset.name);

                card.dataset.category = category;
                card.dataset.price = String(price);
                card.dataset.popularity = String(popularity);
                card.dataset.newest = String(card.dataset.menuId || index + 1);

                if (!card.querySelector('.menu-card-badges')) {
                    card.insertAdjacentHTML('afterbegin', `
                        <div class="menu-card-badges">
                            <span class="price-badge">$${price.toFixed(2)}</span>
                            ${discount ? '<span class="discount-badge">20% OFF</span>' : ''}
                            <button class="fav-btn" type="button" aria-label="Add to favorites"><i class="fa-regular fa-heart"></i></button>
                        </div>
                    `);
                }

                if (!card.querySelector('.menu-card-meta')) {
                    card.insertAdjacentHTML('beforeend', `
                        <div class="menu-card-meta">
                            <span class="menu-stars">⭐⭐⭐⭐⭐</span>
                            <span class="menu-time"><i class="fa-solid fa-clock"></i> 20 min</span>
                        </div>
                    `);
                }

                const actionButton = card.querySelector('button.btn-warning, a.btn-warning');
                if (actionButton && !actionButton.classList.contains('menu-order-btn')) {
                    actionButton.classList.add('menu-order-btn');
                    actionButton.innerHTML = '<i class="fa-solid fa-cart-shopping"></i> Add to Cart';
                }

                // Hide legacy price paragraph if present (keeps only the price badge)
                const priceParas = Array.from(card.querySelectorAll('p'));
                priceParas.forEach(p => {
                    const text = p.textContent.trim();
                    if (/^\$/.test(text)) {
                        p.classList.add('legacy-price');
                    }
                });

                // Move the action button to the end of the card so it appears at the bottom
                const finalAction = card.querySelector('.menu-order-btn');
                if (finalAction) {
                    card.appendChild(finalAction);
                }

                const favoriteButton = card.querySelector('.fav-btn');
                if (favoriteButton) {
                    favoriteButton.addEventListener('click', (event) => {
                        event.stopPropagation();
                        favoriteButton.classList.toggle('is-favorited');
                        const icon = favoriteButton.querySelector('i');
                        icon.classList.toggle('fa-solid', favoriteButton.classList.contains('is-favorited'));
                        icon.classList.toggle('fa-regular', !favoriteButton.classList.contains('is-favorited'));
                    });
                }
            });
        };

        const filterMenuCards = (query = menuState.query) => {
            const cleanQuery = String(query || menuState.query).trim().toLowerCase();
            menuState.query = cleanQuery;

            const menuCardContainer = document.querySelector('.menu_card');
            const visibleCards = menuCards.filter((card) => {
                const text = card.innerText.toLowerCase();
                const matchesQuery = !cleanQuery || text.includes(cleanQuery);
                const category = card.dataset.category || 'burger';
                const matchesCategory = menuState.category === 'all' || category === menuState.category;
                return matchesQuery && matchesCategory;
            });

            const sortedCards = [...visibleCards].sort((a, b) => {
                if (menuState.sort === 'price-asc') return Number(a.dataset.price || 0) - Number(b.dataset.price || 0);
                if (menuState.sort === 'price-desc') return Number(b.dataset.price || 0) - Number(a.dataset.price || 0);
                if (menuState.sort === 'popularity') return Number(b.dataset.popularity || 0) - Number(a.dataset.popularity || 0);
                if (menuState.sort === 'newest') return Number(a.dataset.newest || 0) - Number(b.dataset.newest || 0);
                return 0;
            });

            if (menuCardContainer) {
                menuCardContainer.innerHTML = '';
                if (!sortedCards.length) {
                    menuCardContainer.innerHTML = '<div class="menu-empty">No delicious match found. Try another search or category.</div>';
                } else {
                    sortedCards.forEach((card) => {
                        card.classList.remove('is-hidden');
                        card.classList.toggle('is-match', Boolean(cleanQuery));
                        menuCardContainer.appendChild(card);
                    });
                }
            }

            menuCards.forEach((card) => {
                if (!sortedCards.includes(card)) {
                    card.classList.add('is-hidden');
                }
            });
        };

        const renderSearchResults = (query) => {
            const cleanQuery = query.trim().toLowerCase();

            if (!cleanQuery) {
                searchResults.innerHTML = '<div class="search-empty">Type a dish name or keyword.</div>';
                return;
            }

            const matches = menuCards.filter((card) => card.innerText.toLowerCase().includes(cleanQuery));

            if (!matches.length) {
                searchResults.innerHTML = '<div class="search-empty">No matching menu items found.</div>';
                return;
            }

            searchResults.innerHTML = '';
            matches.forEach((card) => {
                const title = card.querySelector('h3')?.textContent.trim() || 'Menu item';
                const button = document.createElement('button');
                button.className = 'search-result-item';
                button.type = 'button';
                button.dataset.title = title;
                button.textContent = title;
                searchResults.appendChild(button);
            });

            document.querySelectorAll('.search-result-item').forEach((button) => {
                button.addEventListener('click', () => {
                    const title = button.getAttribute('data-title');
                    filterMenuCards(title);
                    searchInput.value = title;
                    searchResults.innerHTML = '<div class="search-empty">Showing matching items.</div>';
                    searchPanel.classList.remove('show');

                    const menuSection = document.getElementById('Menu');
                    if (menuSection) {
                        menuSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            });
        };

        const updateCartBadge = (quantity) => {
            const cartBadge = document.querySelector('.cart-badge');
            if (cartBadge) {
                cartBadge.textContent = String(quantity || 0);
            }
        };

        document.querySelectorAll('.menu-order-btn').forEach((button) => {
            button.addEventListener('click', async () => {
                const menuId = Number(button.dataset.menuId || 0);
                if (!menuId) {
                    return;
                }

                const formData = new URLSearchParams();
                formData.append('action', 'add');
                formData.append('menu_id', String(menuId));
                formData.append('csrf_token', <?= json_encode(customer_csrf_token(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>);

                try {
                    const response = await fetch('cart.php?action=add', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData.toString(),
                        credentials: 'same-origin'
                    });

                    const data = await response.json();

                    if (data && data.success) {
                        const cartCount = Number(data.cart_count ?? data.quantity ?? 0);
                        updateCartBadge(cartCount);
                        button.classList.add('is-added');
                        const previousText = button.innerHTML;
                        button.innerHTML = '<i class="fa-solid fa-check"></i> Added';
                        setTimeout(() => {
                            button.innerHTML = previousText;
                            button.classList.remove('is-added');
                        }, 900);
                    } else {
                        alert(data && data.message ? data.message : 'Unable to add item to cart.');
                    }
                } catch (error) {
                    alert('Unable to add item to cart.');
                }
            });
        });

        const handleScroll = () => {
            navbar.classList.toggle('scrolled', window.scrollY > 25);
            setActiveLink();
        };

        window.addEventListener('scroll', handleScroll, { passive: true });
        window.addEventListener('load', handleScroll);

        setupMenuCardUI();
        filterMenuCards('');

        const menuSearchInput = document.getElementById('menuSearchBar');
        const filterButtons = document.querySelectorAll('.filter-btn');
        const sortSelect = document.getElementById('sortSelect');

        if (menuSearchInput) {
            menuSearchInput.addEventListener('input', (event) => {
                menuState.query = event.target.value.trim().toLowerCase();
                filterMenuCards(menuState.query);
            });
        }

        filterButtons.forEach((button) => {
            button.addEventListener('click', () => {
                filterButtons.forEach((item) => item.classList.remove('active'));
                button.classList.add('active');
                menuState.category = button.getAttribute('data-filter') || 'all';
                filterMenuCards(menuState.query);
            });
        });

        if (sortSelect) {
            sortSelect.addEventListener('change', (event) => {
                menuState.sort = event.target.value;
                filterMenuCards(menuState.query);
            });
        }

        if (toggleButton && navLinks) {
            const setMobileMenuState = (isOpen) => {
                navLinks.classList.toggle('show', isOpen);
                toggleButton.classList.toggle('open', isOpen);
                toggleButton.setAttribute('aria-expanded', String(isOpen));
                if (window.innerWidth <= 768) {
                    navbar.classList.toggle('menu-open', isOpen);
                    document.body.classList.toggle('mobile-menu-open', isOpen);
                }
            };

            toggleButton.addEventListener('click', () => {
                setMobileMenuState(!navLinks.classList.contains('show'));
            });

            navItems.forEach((link) => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 768) {
                        setMobileMenuState(false);
                    }
                });
            });

            if (mobileMenuBackdrop) {
                mobileMenuBackdrop.addEventListener('click', () => setMobileMenuState(false));
            }

            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) {
                    navbar.classList.remove('menu-open');
                    document.body.classList.remove('mobile-menu-open');
                }
            });
        }

        if (searchToggle && searchPanel && searchInput) {
            searchToggle.addEventListener('click', () => {
                searchPanel.classList.toggle('show');
                setTimeout(() => searchInput.focus(), 100);
            });

            searchInput.addEventListener('input', (event) => {
                const value = event.target.value;
                filterMenuCards(value);
                renderSearchResults(value);
            });

            document.addEventListener('click', (event) => {
                if (!searchPanel.contains(event.target) && !searchToggle.contains(event.target)) {
                    searchPanel.classList.remove('show');
                }
            });
        }

        /* ===== Order section enhancements (non-destructive) ===== */
        (function(){
            document.addEventListener('DOMContentLoaded', function(){
                const orderSection = document.getElementById('Order');
                if(!orderSection) return;

                // Insert premium header above existing h1
                const header = document.createElement('div');
                header.className = 'order-header fade-up';
                header.innerHTML = '<div class="label">PLACE YOUR ORDER</div>\n                    <h2>Fresh Food, Fast Delivery</h2>\n                    <p>Quick, secure and premium delivery — place your order now and enjoy hot meals at your door.</p>';
                const oldH1 = orderSection.querySelector('h1');
                if(oldH1) orderSection.insertBefore(header, oldH1);

                // Add icons to inputs
                const inputGroups = orderSection.querySelectorAll('.input');
                inputGroups.forEach(group => {
                    const lab = group.querySelector('label');
                    const txt = lab ? lab.textContent.toLowerCase() : '';
                    let icon = 'fa-user';
                    if(txt.includes('email')) icon = 'fa-envelope';
                    else if(txt.includes('number')) icon = 'fa-phone';
                    else if(txt.includes('how') || txt.includes('much') || txt.includes('quantity')) icon = 'fa-hashtag';
                    else if(txt.includes('order')) icon = 'fa-utensils';
                    else if(txt.includes('adress') || txt.includes('address')) icon = 'fa-map-marker-alt';
                    const i = document.createElement('i'); i.className = 'fa ' + icon;
                    group.insertBefore(i, group.firstChild);
                });

                // Build payment method cards
                const form = orderSection.querySelector('form');
                const payBox = document.createElement('div'); payBox.className = 'payment-methods';
                payBox.setAttribute('role','radiogroup');
                payBox.setAttribute('aria-label','Payment methods');
                const methods = [
                    {key:'cod', icon:'fa-money-bill-wave', label:'Cash on Delivery'},
                    {key:'card', icon:'fa-credit-card', label:'Credit / Debit Card'},
                    {key:'jazz', icon:'fa-mobile-alt', label:'JazzCash'},
                    {key:'easypaisa', icon:'fa-mobile', label:'EasyPaisa'}
                ];
                methods.forEach(m=>{
                    const c = document.createElement('div'); c.className = 'pay-card'; c.dataset.key = m.key;
                    c.setAttribute('role','radio');
                    c.setAttribute('tabindex','0');
                    c.setAttribute('aria-checked','false');
                    c.innerHTML = '<i class="fa '+m.icon+'"></i><div class="label">'+m.label+'</div>';
                    const selectCard = ()=>{
                        document.querySelectorAll('.pay-card').forEach(el=>{ el.classList.remove('selected'); el.setAttribute('aria-checked','false'); });
                        c.classList.add('selected');
                        c.setAttribute('aria-checked','true');
                        c.focus();
                    };
                    c.addEventListener('click', selectCard);
                    c.addEventListener('keydown', (ev)=>{ if(ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); selectCard(); } });
                    payBox.appendChild(c);
                });
                form.appendChild(payBox);

                // Coupon UI
                const couponRow = document.createElement('div'); couponRow.className = 'coupon-row';
                couponRow.innerHTML = '<input type="text" placeholder="Enter Coupon Code" id="couponCode" aria-label="Coupon Code"><button type="button" id="applyCoupon">Apply</button><div id="couponMsg" role="status" aria-live="polite" style="display:none;margin-left:8px;color:#fff"></div><div class="coupon-hint">Use <strong>SAVE10</strong> for 10% off or <strong>FREEDEL</strong> for free delivery.</div>';
                form.appendChild(couponRow);

                // Order summary card
                const orderMain = orderSection.querySelector('.order_main');
                const summaryWrap = document.createElement('div'); summaryWrap.className = 'order-summary-wrapper fade-up';
                summaryWrap.innerHTML = '<div class="order-summary">\n                    <h3>Your Order</h3>\n                    <div class="summary-items"></div>\n                    <div class="summary-row"><div>Subtotal</div><div class="subtotal">$0.00</div></div>\n                    <div class="summary-row"><div>Delivery</div><div class="delivery">$2.50</div></div>\n                    <div class="summary-row"><div>Tax</div><div class="tax">$0.00</div></div>\n                    <div class="summary-row"><div>Discount</div><div class="discount">-$0.00</div></div>\n                    <div class="summary-row total"><div>Total</div><div class="total-amount">$0.00</div></div>\n                    <button class="place-order-btn" id="placeOrderBtn"><i class="fa fa-shopping-cart"></i> Place Order</button>\n                    <div class="trust-badges">\n                      <div class="trust-badge"><i class="fa fa-lock"></i> Secure Checkout</div>\n                      <div class="trust-badge"><i class="fa fa-truck"></i> 30 Minutes Delivery</div>\n                      <div class="trust-badge"><i class="fa fa-star"></i> Premium Quality</div>\n                      <div class="trust-badge"><i class="fa fa-headset"></i> 24/7 Support</div>\n                    </div>\n                </div>';
                orderMain.appendChild(summaryWrap);

                // Hide original CTA anchor (visual only)
                const origBtn = orderSection.querySelector('a.btn.btn-warning'); if(origBtn) origBtn.style.display = 'none';

                // Totals logic
                const qtyInput = orderSection.querySelector('[id="how much"]');
                const orderInput = orderSection.querySelector('#order');
                const subtotalEl = summaryWrap.querySelector('.subtotal');
                const deliveryEl = summaryWrap.querySelector('.delivery');
                const taxEl = summaryWrap.querySelector('.tax');
                const discountEl = summaryWrap.querySelector('.discount');
                const totalEl = summaryWrap.querySelector('.total-amount');
                const summaryItems = summaryWrap.querySelector('.summary-items');
                let couponDiscount = 0;
                let freeDelivery = false;

                function format(n){ return '$' + n.toFixed(2); }

                function updateSummary(){
                    const qty = Math.max(1, Number(qtyInput?.value) || 1);
                    const orderName = (orderInput?.value || '').trim() || 'Selected Item';
                    const selectedCard = document.querySelector('.menu .card.is-selected') || document.querySelector('.menu .card');
                    let price = selectedCard && selectedCard.dataset.price ? Number(selectedCard.dataset.price) : 9.99;
                    const subtotal = price * qty;
                    const delivery = freeDelivery ? 0 : 2.5;
                    const tax = +(subtotal * 0.05);
                    const discount = +(couponDiscount > 0 ? subtotal * couponDiscount : 0);
                    const total = Math.max(0, subtotal + delivery + tax - discount);
                    subtotalEl.textContent = format(subtotal);
                    deliveryEl.textContent = format(delivery);
                    taxEl.textContent = format(tax);
                    discountEl.textContent = '-' + format(discount);
                    totalEl.textContent = format(total);
                    summaryItems.innerHTML = '<div class="summary-row"><div>' + orderName + ' x ' + qty + '</div><div>' + format(price) + '</div></div>';
                }
                updateSummary();
                if(qtyInput) qtyInput.addEventListener('input', updateSummary);
                if(orderInput) orderInput.addEventListener('input', updateSummary);

                // Coupon logic
                document.getElementById('applyCoupon').addEventListener('click', function(){
                    const code = document.getElementById('couponCode').value.trim().toUpperCase();
                    const msg = document.getElementById('couponMsg');
                    if(!code){ msg.style.display='block'; msg.textContent='Enter coupon code'; msg.style.color='#ffc107'; return; }
                    freeDelivery = false;
                    if(code === 'SAVE10'){ couponDiscount = 0.10; msg.style.display='block'; msg.textContent = 'Coupon applied: 10% off'; msg.style.color = '#8fd18f'; }
                    else if(code === 'FREEDEL'){ couponDiscount = 0; freeDelivery = true; msg.style.display='block'; msg.textContent = 'Coupon applied: Free delivery'; msg.style.color = '#8fd18f'; }
                    else { couponDiscount = 0; msg.style.display='block'; msg.textContent = 'Invalid coupon'; msg.style.color = '#ff8a8a'; }
                    updateSummary();
                });

                // Place order button behavior + validation
                const placeBtn = document.getElementById('placeOrderBtn');
                placeBtn.addEventListener('click', function(){
                    const name = orderSection.querySelector('#name')?.value.trim();
                    const email = orderSection.querySelector('#email')?.value.trim();
                    const number = orderSection.querySelector('#number')?.value.trim();
                    const qty = Number(orderSection.querySelector('[id="how much"]')?.value) || 1;
                    const payment = document.querySelector('.pay-card.selected');
                    if(!name){ alert('Please enter your name'); return; }
                    if(!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){ alert('Please enter a valid email'); return; }
                    if(!number || number.length < 6){ alert('Please enter a valid phone number'); return; }
                    if(!payment){ alert('Please select a payment method'); return; }
                    if(qty < 1){ alert('Quantity must be at least 1'); return; }
                    // loading animation
                    placeBtn.classList.add('loading');
                    placeBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
                    setTimeout(()=>{
                        placeBtn.classList.remove('loading');
                        placeBtn.innerHTML = '<i class="fa fa-check"></i> Order Placed';
                        showSuccessModal();
                    }, 1400);
                });

                // Success modal
                function showSuccessModal(){
                    const modal = document.createElement('div'); modal.className = 'order-success-modal';
                    modal.innerHTML = '<div class="modal-box" style="max-width:420px;padding:22px;border-radius:12px;background:#111;color:#fff;text-align:center;">\n                        <h2 style="color:#8fd18f;margin-bottom:8px">✔ Order Placed Successfully</h2>\n                        <p style="color:#bfc1c5;margin-bottom:10px">Estimated delivery: 25-35 minutes</p>\n                        <p style="color:#bfc1c5;margin-bottom:18px">Order #: ' + (Math.floor(Math.random()*900000)+100000) + '</p>\n                        <button id="continueShopping" class="place-order-btn">Continue Shopping</button>\n                    </div>';
                    Object.assign(modal.style,{position:'fixed',left:0,top:0,right:0,bottom:0,display:'flex',alignItems:'center',justifyContent:'center',background:'rgba(0,0,0,0.75)',zIndex:99999});
                    document.body.appendChild(modal);
                    document.getElementById('continueShopping').addEventListener('click', ()=>{ modal.remove(); window.scrollTo({top:0,behavior:'smooth'}); });
                }

            });
        })();
    </script>

    <footer class="premium-footer" aria-label="Restaurant footer">
        <div class="footer-shell container-xl">
            <div class="footer-top">
                <div class="footer-brand-col">
                    <a href="#home" class="footer-logo" aria-label="Fast Food home">
                        <img src="https://static.vecteezy.com/system/resources/thumbnails/019/607/567/small/fast-food-vector-clipart-design-graphic-clipart-design-free-png.png" alt="Fast Food restaurant logo">
                    </a>
                    <p class="footer-brand-copy">Serving delicious food made with fresh ingredients, passion, and love. Experience premium taste delivered straight to your doorstep.</p>
                    <div class="footer-social" role="navigation" aria-label="Social media links">
                        <a href="#" class="footer-social-link" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="footer-social-link" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="footer-social-link" aria-label="TikTok"><i class="fab fa-tiktok"></i></a>
                        <a href="#" class="footer-social-link" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>

                <div class="footer-links-col">
                    <h3>QUICK LINKS</h3>
                    <ul>
                        <li><a href="#home">Home</a></li>
                        <li><a href="#About">About Us</a></li>
                        <li><a href="#Menu">Our Menu</a></li>
                        <li><a href="#Gallery">Gallery</a></li>
                        <li><a href="#Review">Reviews</a></li>
                        <li><a href="#Order">Order Now</a></li>
                        <li><a href="#home">Contact Us</a></li>
                    </ul>
                </div>

                <div class="footer-info-col">
                    <h3>INFORMATION</h3>
                    <ul>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms & Conditions</a></li>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Delivery Information</a></li>
                        <li><a href="#">Refund Policy</a></li>
                        <li><a href="#">Customer Support</a></li>
                    </ul>
                </div>

                <div class="footer-contact-col">
                    <h3>CONTACT US</h3>
                    <ul class="footer-contact-list">
                        <li><i class="fa-solid fa-location-dot"></i><span>123 Premium Avenue, City</span></li>
                        <li><i class="fa-solid fa-phone"></i><a href="tel:+1234567890">+1 234 567 890</a></li>
                        <li><i class="fa-solid fa-envelope"></i><a href="mailto:info@fastfood.com">info@fastfood.com</a></li>
                        <li><i class="fa-solid fa-clock"></i><span>Mon – Sun 10:00 AM – 11:00 PM</span></li>
                    </ul>
                </div>
            </div>

            <!-- <div class="footer-mid">
                <div class="footer-newsletter">
                    <div>
                        <span class="footer-subtitle">Stay Updated With Us</span>
                        <p>Subscribe to receive our latest offers, special deals and delicious updates.</p>
                    </div>
                    <form class="footer-newsletter-form" action="#" method="post">
                        <input type="email" placeholder="Enter your email" aria-label="Email address" required>
                        <button type="submit">Subscribe</button>
                    </form>
                </div>

                <div class="footer-cta-pay">
                    <div class="footer-cta-box">
                        <span class="cta-label">Hungry?</span>
                        <p>Order your favorite meal today.</p>
                        <a href="#Order" class="footer-cta-btn">ORDER NOW</a>
                    </div>
                    <div class="footer-payments">
                        <span>Accepted Payments</span>
                        <div class="payment-grid">
                            <div class="payment-item"><i class="fa-solid fa-money-bill-wave"></i><span>Cash on Delivery</span></div>
                            <div class="payment-item"><i class="fa-brands fa-cc-visa"></i><span>Visa</span></div>
                            <div class="payment-item"><i class="fa-brands fa-cc-mastercard"></i><span>Mastercard</span></div>
                            <div class="payment-item"><i class="fa-solid fa-mobile-screen-button"></i><span>JazzCash</span></div>
                            <div class="payment-item"><i class="fa-solid fa-wallet"></i><span>EasyPaisa</span></div>
                        </div>
                    </div>
                </div>
            </div> -->
        </div>

        <div class="footer-bottom">
            <hr>
            <div class="footer-bottom-row">
                <p>© 2026 Fast Food. All Rights Reserved.</p>
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a>
                    <span>|</span>
                    <a href="#">Terms & Conditions</a>
                </div>
            </div>
        </div>
    </footer>
</body>

</html>