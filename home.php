<?php
session_start();
require_once './db.php';

// Check for remember me cookie
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = base64_decode($_COOKIE['remember_token']);
    $parts = explode(':', $token);
    
    if (count($parts) == 2) {
        $user_id = $parts[0];
        $user_type = $parts[1];
        
        try {
            if ($user_type == 'student') {
                $stmt = $conn->prepare("SELECT student_id as user_id, student_name as name, student_email as email, 'student' as user_type FROM student WHERE student_id = ?");
            } else {
                $stmt = $conn->prepare("SELECT owner_id as user_id, owner_name as name, owner_email as email, 'owner' as user_type FROM owner WHERE owner_id = ?");
            }
            
            $stmt->execute([$user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_type'] = $user['user_type'];
            }
        } catch (PDOException $e) {
            // Invalid token, delete cookie
            setcookie('remember_token', '', time() - 3600, '/');
        }
    }
}

$currentUser = null;
if (isset($_SESSION['user_id'])) {
    $currentUser = [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'],
        'type' => $_SESSION['user_type']
    ];
}

// Clear any success messages
if (isset($_SESSION['success'])) {
    unset($_SESSION['success']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UniHousing - Student Housing Platform</title>
    <link rel="stylesheet" href="./stylen.css">
    <link rel="stylesheet" href="./logo-animation.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Preloader -->
    <div class="preloader">
        <img src="./images/main_file-01.png" alt="UniHousing Logo">
    </div>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="home.php">
                        <img src="./images/logopro.png" alt="UniHousing Logo">
                    </a>
                </div>
                <nav class="nav-menu">
                    <ul>
                        <li><a href="home.php" class="active">Home</a></li>
                        <li><a href="offers.html">Offers</a></li>
                        <li><a href="demands.html">Demands</a></li>
                        <li><a href="about.html">About</a></li>
                        <li><a href="contact.html">Contact</a></li>
                    </ul>
                </nav>
                <div class="auth-buttons">
                    <?php if ($currentUser): ?>
                        <a href="Profile.php" class="btn btn-primary">My Profile</a>
                        <a href="logout.php" class="btn btn-outline">Logout</a>
                    <?php else: ?>
                        <a href="./login.php" class="btn btn-outline">Login</a>
                        <a href="./signUp.php" class="btn btn-primary">Sign Up</a>
                    <?php endif; ?>
                </div>
                <div class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </div>
    </header>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <div class="logo">
                <a href="index.html">
                    <img src="./images/logopro.png" alt="UniHousing Logo">
                </a>
            </div>
            <div class="mobile-menu-close" id="mobileMenuClose">
                <i class="fas fa-times"></i>
            </div>
        </div>
        <nav class="mobile-menu-nav">
            <ul>
                <li><a href="index.html" class="active">Home</a></li>
                <li><a href="offers.html">Offers</a></li>
                <li><a href="demands.html">Demands</a></li>
                <li><a href="about.html">About</a></li>
                <li><a href="contact.html">Contact</a></li>
            </ul>
        </nav>
        <div class="mobile-menu-auth">
            <?php if ($currentUser): ?>
                <a href="logout.php" class="btn btn-outline">Logout</a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline">Login</a>
                <a href="signUp.php" class="btn btn-primary">Sign Up</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-background"></div>
        <div class="container">
            <div class="hero-content">
                <h1>Find Your Perfect Student Housing</h1>
                <p>Connect with apartment owners and find the ideal place near your university.</p>
                
                <div class="search-box">
                    <div class="search-input">
                        <i class="fas fa-map-marker-alt"></i>
                        <input type="text" placeholder="University or location">
                    </div>
                    <div class="search-input">
                        <i class="fas fa-home"></i>
                        <select>
                            <option value="">Property type</option>
                            <option value="apartment">Apartment</option>
                            <option value="house">House</option>
                            <option value="room">Room</option>
                            <option value="dormitory">Dormitory</option>
                        </select>
                    </div>
                    <button class="btn btn-primary">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistics Section -->
    <section class="statistics">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="stat-content">
                        <h3>10,000+</h3>
                        <p>Properties Listed</p>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-content">
                        <h3>50,000+</h3>
                        <p>Happy Students</p>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="stat-content">
                        <h3>500+</h3>
                        <p>Universities Covered</p>
                    </div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-content">
                        <h3>4.8/5</h3>
                        <p>User Rating</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Navigation Cards -->
    <section class="nav-cards">
        <div class="container">
            <div class="cards-grid">
                <a href="offers.html" class="nav-card">
                    <div class="icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <h2>Browse Offers</h2>
                    <p>Explore available apartments and houses posted by owners.</p>
                </a>
                <a href="demands.html" class="nav-card">
                    <div class="icon green">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h2>Student Demands</h2>
                    <p>View what students are looking for and post your own requirements.</p>
                </a>
                <a href="profile.html" class="nav-card">
                    <div class="icon purple">
                        <i class="fas fa-star"></i>
                    </div>
                    <h2>Your Profile</h2>
                    <p>Manage your profile, view saved properties, and track applications.</p>
                </a>
            </div>
        </div>
    </section>

    <!-- Recommended Section -->
    <section class="property-section">
        <div class="container">
            <div class="section-header">
                <h2>Recommended For You</h2>
                <a href="offers.html" class="view-all">View all</a>
            </div>
            
            <div class="property-grid">
                <!-- Property Card 1 -->
                <div class="property-card">
                    <div class="property-image">
                        <img src="https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Modern Studio Apartment">
                        <button class="favorite-btn">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>
                    <div class="property-content">
                        <div class="property-header">
                            <h3>Modern Studio Apartment</h3>
                            <p class="price">$650/mo</p>
                        </div>
                        <div class="property-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <p>Near Stanford University, Palo Alto</p>
                        </div>
                        <div class="property-features">
                            <div class="feature">
                                <i class="fas fa-bed"></i>
                                <span>1 Bed</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-bath"></i>
                                <span>1 Bath</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-vector-square"></i>
                                <span>35 m²</span>
                            </div>
                        </div>
                        <a href="offer-details.html?id=1" class="btn btn-primary btn-full">View Details</a>
                    </div>
                </div>

                <!-- Property Card 2 -->
                <div class="property-card">
                    <div class="property-image">
                        <img src="https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Shared 2-Bedroom Apartment">
                        <button class="favorite-btn active">
                            <i class="fas fa-heart"></i>
                        </button>
                    </div>
                    <div class="property-content">
                        <div class="property-header">
                            <h3>Shared 2-Bedroom Apartment</h3>
                            <p class="price">$450/mo</p>
                        </div>
                        <div class="property-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <p>5 min from MIT, Cambridge</p>
                        </div>
                        <div class="property-features">
                            <div class="feature">
                                <i class="fas fa-bed"></i>
                                <span>1 Bed</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-bath"></i>
                                <span>1 Bath</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-vector-square"></i>
                                <span>20 m²</span>
                            </div>
                        </div>
                        <a href="offer-details.html?id=2" class="btn btn-primary btn-full">View Details</a>
                    </div>
                </div>

                <!-- Property Card 3 -->
                <div class="property-card">
                    <div class="property-image">
                        <img src="https://images.unsplash.com/photo-1560185007-5f0bb1866cab?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Luxury Student Housing">
                        <button class="favorite-btn">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>
                    <div class="property-content">
                        <div class="property-header">
                            <h3>Luxury Student Housing</h3>
                            <p class="price">$850/mo</p>
                        </div>
                        <div class="property-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <p>UCLA Campus Area, Los Angeles</p>
                        </div>
                        <div class="property-features">
                            <div class="feature">
                                <i class="fas fa-bed"></i>
                                <span>1 Bed</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-bath"></i>
                                <span>1 Bath</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-vector-square"></i>
                                <span>40 m²</span>
                            </div>
                        </div>
                        <a href="offer-details.html?id=3" class="btn btn-primary btn-full">View Details</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Nearby University Section -->
    <section class="property-section">
        <div class="container">
            <div class="section-header">
                <h2>Nearby Your University</h2>
                <a href="offers.html?filter=nearby" class="view-all">View all</a>
            </div>
            
            <div class="property-grid">
                <!-- Property Card 4 -->
                <div class="property-card">
                    <div class="property-image">
                        <img src="https://images.unsplash.com/photo-1560185127-6ed189bf02f4?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Cozy Room in Shared House">
                        <button class="favorite-btn">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>
                    <div class="property-content">
                        <div class="property-header">
                            <h3>Cozy Room in Shared House</h3>
                            <p class="price">$380/mo</p>
                        </div>
                        <div class="property-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <p>University of Washington Area, Seattle</p>
                        </div>
                        <div class="property-features">
                            <div class="feature">
                                <i class="fas fa-bed"></i>
                                <span>1 Bed</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-bath"></i>
                                <span>2 Bath</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-vector-square"></i>
                                <span>18 m²</span>
                            </div>
                        </div>
                        <a href="offer-details.html?id=4" class="btn btn-primary btn-full">View Details</a>
                    </div>
                </div>

                <!-- Property Card 5 -->
                <div class="property-card">
                    <div class="property-image">
                        <img src="https://images.unsplash.com/photo-1560185893-a55cbc8c57e8?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Modern 1-Bedroom Apartment">
                        <button class="favorite-btn">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>
                    <div class="property-content">
                        <div class="property-header">
                            <h3>Modern 1-Bedroom Apartment</h3>
                            <p class="price">$750/mo</p>
                        </div>
                        <div class="property-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <p>NYU Area, New York City</p>
                        </div>
                        <div class="property-features">
                            <div class="feature">
                                <i class="fas fa-bed"></i>
                                <span>1 Bed</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-bath"></i>
                                <span>1 Bath</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-vector-square"></i>
                                <span>45 m²</span>
                            </div>
                        </div>
                        <a href="offer-details.html?id=5" class="btn btn-primary btn-full">View Details</a>
                    </div>
                </div>

                <!-- Property Card 6 -->
                <div class="property-card">
                    <div class="property-image">
                        <img src="https://images.unsplash.com/photo-1560185007-5f0bb1866cab?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Budget-Friendly Studio">
                        <button class="favorite-btn">
                            <i class="far fa-heart"></i>
                        </button>
                    </div>
                    <div class="property-content">
                        <div class="property-header">
                            <h3>Budget-Friendly Studio</h3>
                            <p class="price">$550/mo</p>
                        </div>
                        <div class="property-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <p>UC Berkeley Area, Berkeley</p>
                        </div>
                        <div class="property-features">
                            <div class="feature">
                                <i class="fas fa-bed"></i>
                                <span>1 Bed</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-bath"></i>
                                <span>1 Bath</span>
                            </div>
                            <div class="feature">
                                <i class="fas fa-vector-square"></i>
                                <span>40 m²</span>
                            </div>
                        </div>
                        <a href="offer-details.html?id=6" class="btn btn-primary btn-full">View Details</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works Section -->
    <section class="how-it-works">
        <div class="container">
            <h2 class="section-title">How It Works</h2>
            <div class="steps-container">
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3>Create an Account</h3>
                    <p>Sign up as a student or property owner to get started.</p>
                </div>
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>Find or List Properties</h3>
                    <p>Search for housing or list your property for students.</p>
                </div>
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3>Connect & Communicate</h3>
                    <p>Message directly with students or property owners.</p>
                </div>
                <div class="step">
                    <div class="step-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <h3>Secure Your Housing</h3>
                    <p>Finalize details and move into your new home.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials">
        <div class="container">
            <h2 class="section-title">What Our Users Say</h2>
            <div class="testimonial-slider">
                <div class="testimonial">
                    <div class="testimonial-content">
                        <p>"I found my perfect apartment near campus in just two days. The platform made it so easy to connect with property owners!"</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80" alt="Sarah Johnson">
                        <div class="author-info">
                            <h4>Sarah Johnson</h4>
                            <p>Stanford University</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial">
                    <div class="testimonial-content">
                        <p>"As a property owner, I've been able to find reliable student tenants quickly. The verification process gives me peace of mind."</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80" alt="Michael Chen">
                        <div class="author-info">
                            <h4>Michael Chen</h4>
                            <p>Property Owner</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial">
                    <div class="testimonial-content">
                        <p>"The demand posting feature helped me find exactly what I was looking for. Property owners reached out with perfect matches!"</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1976&q=80" alt="James Wilson">
                        <div class="author-info">
                            <h4>James Wilson</h4>
                            <p>UCLA Graduate Student</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="slider-dots">
                <span class="dot active"></span>
                <span class="dot"></span>
                <span class="dot"></span>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Find Your Perfect Student Housing?</h2>
                <p>Join thousands of students and property owners on our platform.</p>
                <div class="cta-buttons">
                    <?php if (!$currentUser): ?>
                        <a href="signUp.php" class="btn btn-primary">Sign Up Now</a>
                        <a href="about.html" class="btn btn-outline">Learn More</a>
                    <?php else: ?>
                        <a href="offers.html" class="btn btn-primary">Browse Properties</a>
                        <a href="demands.html" class="btn btn-outline">Post Requirements</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <a href="index.html">Uni<span>Housing</span></a>
                    <p>Connecting students with their perfect housing solutions.</p>
                </div>
                <div class="footer-links">
                    <div class="footer-column">
                        <h3>Quick Links</h3>
                        <ul>
                            <li><a href="index.html">Home</a></li>
                            <li><a href="offers.html">Offers</a></li>
                            <li><a href="demands.html">Demands</a></li>
                            <li><a href="about.html">About Us</a></li>
                            <li><a href="contact.html">Contact</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h3>For Students</h3>
                        <ul>
                            <li><a href="offers.html">Find Housing</a></li>
                            <li><a href="demands.html">Post Requirements</a></li>
                            <li><a href="#">Roommate Finder</a></li>
                            <li><a href="#">University Guides</a></li>
                            <li><a href="#">Student Resources</a></li>
                        </ul>
                    </div>
                    <div class="footer-column">
                        <h3>For Owners</h3>
                        <ul>
                            <li><a href="offers.html?create=true">List Property</a></li>
                            <li><a href="demands.html">View Student Demands</a></li>
                            <li><a href="#">Landlord Resources</a></li>
                            <li><a href="#">Verification Process</a></li>
                            <li><a href="#">Success Stories</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2023 UniHousing. All rights reserved.</p>
                <div class="social-links">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <script src="./main.js"></script>
    <script src="./logo-animation.js"></script>
</body>
</html>
