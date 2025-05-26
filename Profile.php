<?php
session_start();
require_once './db.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "Please login to view your profile";
    header("Location: login.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'];
$user_data = [];
$profile_picture = null;

try {
    if ($user_type == 'student') {
        $stmt = $conn->prepare("
            SELECT s.*, p.picture_url 
            FROM student s
            LEFT JOIN picture p ON s.picture_id = p.picture_id
            WHERE s.student_id = ?
        ");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get wishlist/favorites
        $stmt = $conn->prepare("
            SELECT h.*, c.city_name, pt.proprety_type_name, 
                   (SELECT pp.proprety_pictures_name FROM proprety_pictures pp 
                    JOIN house_property_pictures hpp ON pp.proprety_pictures_id = hpp.proprety_pictures_id 
                    WHERE hpp.house_id = h.house_id LIMIT 1) as image_url
            FROM student_house sh
            JOIN house h ON sh.house_id = h.house_id
            JOIN city c ON h.city_id = c.city_id
            JOIN proprety_type pt ON h.proprety_type_id = pt.proprety_type_id
            WHERE sh.student_id = ?
        ");
        $stmt->execute([$user_id]);
        $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } else { // owner
        $stmt = $conn->prepare("
            SELECT o.*, p.picture_url 
            FROM owner o
            LEFT JOIN picture p ON p.owner_id = o.owner_id
            WHERE o.owner_id = ?
        ");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get owner's properties
        $stmt = $conn->prepare("
            SELECT h.*, c.city_name, pt.proprety_type_name,
                   (SELECT pp.proprety_pictures_name FROM proprety_pictures pp 
                    JOIN house_property_pictures hpp ON pp.proprety_pictures_id = hpp.proprety_pictures_id 
                    WHERE hpp.house_id = h.house_id LIMIT 1) as image_url
            FROM house h
            JOIN city c ON h.city_id = c.city_id
            JOIN proprety_type pt ON h.proprety_type_id = pt.proprety_type_id
            WHERE h.owner_id = ?
        ");
        $stmt->execute([$user_id]);
        $listings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Get all cities for dropdown
    $stmt = $conn->prepare("SELECT city_id, city_name FROM city");
    $stmt->execute();
    $cities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all property types for dropdown
    $stmt = $conn->prepare("SELECT proprety_type_id, proprety_type_name FROM proprety_type");
    $stmt->execute();
    $property_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Error fetching profile data: " . $e->getMessage();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($name) || empty($email)) {
        $_SESSION['error'] = "Name and email are required";
    } else {
        try {
            // Verify current password if changing password
            if (!empty($new_password)) {
                if ($_SESSION['user_type'] == 'student') {
                    $stmt = $conn->prepare("SELECT student_password FROM student WHERE student_id = ?");
                } else {
                    $stmt = $conn->prepare("SELECT owner_password FROM owner WHERE owner_id = ?");
                }
                $stmt->execute([$_SESSION['user_id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stored_password = $result[$_SESSION['user_type'].'_password'];
                
                if (!password_verify($current_password, $stored_password)) {
                    $_SESSION['error'] = "Current password is incorrect";
                    header("Location: Profile.php");
                    exit();
                }
                
                if ($new_password !== $confirm_password) {
                    $_SESSION['error'] = "New passwords do not match";
                    header("Location: Profile.php");
                    exit();
                }
                
                if (strlen($new_password) < 6) {
                    $_SESSION['error'] = "New password must be at least 6 characters";
                    header("Location: Profile.php");
                    exit();
                }
                
                $password_to_update = password_hash($new_password, PASSWORD_DEFAULT);
            }
            
            // Handle profile picture upload
            $filepath = null;
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == 0) {
                $upload_dir = 'uploads/profiles/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                $file_extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array(strtolower($file_extension), $allowed_extensions)) {
                    $filename = uniqid() . '.' . $file_extension;
                    $filepath = $upload_dir . $filename;
                    move_uploaded_file($_FILES['avatar']['tmp_name'], $filepath);
                    
                    // Update picture in database
                    if ($_SESSION['user_type'] == 'student') {
                        $stmt = $conn->prepare("SELECT picture_id FROM student WHERE student_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $picture_id = $stmt->fetch(PDO::FETCH_ASSOC)['picture_id'];
                        
                        if ($picture_id) {
                            $stmt = $conn->prepare("UPDATE picture SET picture_url = ? WHERE picture_id = ?");
                            $stmt->execute([$filepath, $picture_id]);
                        } else {
                            // Create new picture record
                            $stmt = $conn->prepare("SELECT MAX(picture_id) as max_id FROM picture");
                            $stmt->execute();
                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                            $picture_id = ($result['max_id'] ?? 0) + 1;
                            
                            $stmt = $conn->prepare("INSERT INTO picture (picture_id, picture_url) VALUES (?, ?)");
                            $stmt->execute([$picture_id, $filepath]);
                            
                            // Update student with new picture_id
                            $stmt = $conn->prepare("UPDATE student SET picture_id = ? WHERE student_id = ?");
                            $stmt->execute([$picture_id, $_SESSION['user_id']]);
                        }
                    } else {
                        // Check if owner already has a picture
                        $stmt = $conn->prepare("SELECT picture_id FROM picture WHERE owner_id = ?");
                        $stmt->execute([$_SESSION['user_id']]);
                        $picture = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($picture) {
                            $stmt = $conn->prepare("UPDATE picture SET picture_url = ? WHERE picture_id = ?");
                            $stmt->execute([$filepath, $picture['picture_id']]);
                        } else {
                            // Get next picture_id
                            $stmt = $conn->prepare("SELECT MAX(picture_id) as max_id FROM picture");
                            $stmt->execute();
                            $result = $stmt->fetch(PDO::FETCH_ASSOC);
                            $picture_id = ($result['max_id'] ?? 0) + 1;
                            
                            $stmt = $conn->prepare("INSERT INTO picture (picture_id, owner_id, picture_url) VALUES (?, ?, ?)");
                            $stmt->execute([$picture_id, $_SESSION['user_id'], $filepath]);
                        }
                    }
                }
            }
            
            // Update user data
            if ($_SESSION['user_type'] == 'student') {
                $sql = "UPDATE student SET student_name = ?, student_email = ?";
                $params = [$name, $email];
                
                if (!empty($new_password)) {
                    $sql .= ", student_password = ?";
                    $params[] = $password_to_update;
                }
                
                $sql .= " WHERE student_id = ?";
                $params[] = $_SESSION['user_id'];
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            } else {
                $sql = "UPDATE owner SET owner_name = ?, owner_email = ?";
                $params = [$name, $email];
                
                if (!empty($new_password)) {
                    $sql .= ", owner_password = ?";
                    $params[] = $password_to_update;
                }
                
                $sql .= " WHERE owner_id = ?";
                $params[] = $_SESSION['user_id'];
                
                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
            }
            
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['success'] = "Profile updated successfully";
            header("Location: Profile.php");
            exit();
            
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating profile: " . $e->getMessage();
        }
    }
}

// Handle property submission (for owners)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_property']) && $user_type == 'owner') {
    $title = trim($_POST['title']);
    $price = trim($_POST['price']);
    $location = trim($_POST['location']);
    $city_id = $_POST['city'];
    $property_type = $_POST['property_type'];
    $bedrooms = $_POST['bedrooms'];
    $bathrooms = $_POST['bathrooms'];
    $description = trim($_POST['description']);
    
    if (empty($title) || empty($price) || empty($location) || empty($city_id) || empty($property_type)) {
        $_SESSION['error'] = "Please fill in all required fields";
    } else {
        try {
            // Get next house_id
            $stmt = $conn->prepare("SELECT MAX(house_id) as max_id FROM house");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $house_id = ($result['max_id'] ?? 0) + 1;
            
            // Insert new property
            $stmt = $conn->prepare("
                INSERT INTO house 
                (house_id, city_id, proprety_type_id, owner_id, house_title, house_price, house_location, house_badroom, house_bathroom, house_description) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $house_id, 
                $city_id, 
                $property_type, 
                $_SESSION['user_id'], 
                $title, 
                $price, 
                $location, 
                $bedrooms, 
                $bathrooms, 
                $description
            ]);
            
            // Handle property images
            if (isset($_FILES['property_images']) && !empty($_FILES['property_images']['name'][0])) {
                $upload_dir = 'uploads/properties/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Get next proprety_pictures_id
                $stmt = $conn->prepare("SELECT MAX(proprety_pictures_id) as max_id FROM proprety_pictures");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $picture_id = ($result['max_id'] ?? 0) + 1;
                
                foreach ($_FILES['property_images']['name'] as $key => $name) {
                    if ($_FILES['property_images']['error'][$key] == 0) {
                        $file_extension = pathinfo($name, PATHINFO_EXTENSION);
                        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                        
                        if (in_array(strtolower($file_extension), $allowed_extensions)) {
                            $filename = uniqid() . '.' . $file_extension;
                            $filepath = $upload_dir . $filename;
                            move_uploaded_file($_FILES['property_images']['tmp_name'][$key], $filepath);
                            
                            // Insert property picture
                            $stmt = $conn->prepare("INSERT INTO proprety_pictures (proprety_pictures_id, proprety_pictures_name) VALUES (?, ?)");
                            $stmt->execute([$picture_id, $filepath]);
                            
                            // Link property to picture
                            $stmt = $conn->prepare("INSERT INTO house_property_pictures (house_id, proprety_pictures_id, house_property_pictures_date) VALUES (?, ?, NOW())");
                            $stmt->execute([$house_id, $picture_id]);
                            
                            $picture_id++;
                        }
                    }
                }
            }
            
            $_SESSION['success'] = "Property added successfully";
            header("Location: Profile.php");
            exit();
            
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error adding property: " . $e->getMessage();
        }
    }
}

// Get user name and profile picture for display
$user_name = $user_data[$user_type . '_name'] ?? $_SESSION['user_name'] ?? 'User';
$user_email = $user_data[$user_type . '_email'] ?? $_SESSION['user_email'] ?? '';
$profile_picture = $user_data['picture_url'] ?? 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - UniHousing</title>
    <link rel="stylesheet" href="./stylen.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
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
                        <li><a href="home.php">Home</a></li>
                        <li><a href="offers.php">Offers</a></li>
                        <li><a href="demands.php">Demands</a></li>
                        <li><a href="about.php">About</a></li>
                        <li><a href="contact.php">Contact</a></li>
                    </ul>
                </nav>
                <div class="auth-buttons">
                    <a href="Profile.php" class="btn btn-primary">My Profile</a>
                    <a href="logout.php" class="btn btn-outline">Logout</a>
                </div>
                <div class="mobile-menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </div>
        </div>
    </header>

    <!-- Profile Section -->
    <section class="profile-section">
        <div class="container">
            <?php
            // Display error or success messages
            if (isset($_SESSION['error'])) {
                echo '<div class="alert alert-error">' . htmlspecialchars($_SESSION['error']) . '</div>';
                unset($_SESSION['error']);
            }
            if (isset($_SESSION['success'])) {
                echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success']) . '</div>';
                unset($_SESSION['success']);
            }
            ?>
            <div class="profile-grid">
                <!-- Sidebar Navigation -->
                <div class="profile-sidebar">
                    <div class="user-info">
                        <div class="user-avatar">
                            <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="User Avatar">
                            <button class="edit-avatar" onclick="document.getElementById('avatar-upload').click()">
                                <i class="fas fa-camera"></i>
                            </button>
                            <form id="avatar-form" action="Profile.php" method="POST" enctype="multipart/form-data" style="display:none;">
                                <input type="file" id="avatar-upload" name="avatar" onchange="document.getElementById('avatar-form').submit()">
                                <input type="hidden" name="update_profile" value="1">
                                <input type="hidden" name="name" value="<?php echo htmlspecialchars($user_name); ?>">
                                <input type="hidden" name="email" value="<?php echo htmlspecialchars($user_email); ?>">
                            </form>
                        </div>
                        <h2><?php echo htmlspecialchars($user_name); ?></h2>
                        <p class="user-type"><?php echo ucfirst(htmlspecialchars($user_type)); ?></p>
                        <div class="user-rating">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                            <span>4.5 (24 reviews)</span>
                        </div>
                    </div>
                    <nav class="profile-nav">
                        <ul>
                            <li class="active">
                                <a href="#dashboard" data-section="dashboard">
                                    <i class="fas fa-home"></i>
                                    Dashboard
                                </a>
                            </li>
                            <?php if ($user_type == 'owner'): ?>
                            <li>
                                <a href="#my-listings" data-section="my-listings">
                                    <i class="fas fa-list"></i>
                                    My Listings
                                </a>
                            </li>
                            <li>
                                <a href="#add-property" data-section="add-property">
                                    <i class="fas fa-plus-circle"></i>
                                    Add Property
                                </a>
                            </li>
                            <?php else: ?>
                            <li>
                                <a href="#favorites" data-section="favorites">
                                    <i class="fas fa-heart"></i>
                                    Favorites
                                </a>
                            </li>
                            <?php endif; ?>
                            <li>
                                <a href="#messages" data-section="messages">
                                    <i class="fas fa-envelope"></i>
                                    Messages
                                </a>
                            </li>
                            <li>
                                <a href="#settings" data-section="settings">
                                    <i class="fas fa-cog"></i>
                                    Settings
                                </a>
                            </li>
                            <li>
                                <a href="#messages">
                                    <i class="fas fa-envelope"></i>
                                    Messages
                                    <span class="badge">3</span>
                                </a>
                            </li>
                            <li>
                                <a href="#settings">
                                    <i class="fas fa-cog"></i>
                                    Settings
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>

                <!-- Main Content -->
                <div class="profile-content">
                    <!-- Dashboard Section -->
                    <div class="profile-section active" id="dashboard">
                        <h1>Dashboard</h1>
                        <div class="dashboard-stats">
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-home"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>Active Listings</h3>
                                    <p class="stat-number"><?php echo htmlspecialchars($user_email); ?></p>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-eye"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>Total Views</h3>
                                    <p class="stat-number">1,234</p>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>New Messages</h3>
                                    <p class="stat-number">3</p>
                                </div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="stat-info">
                                    <h3>Average Rating</h3>
                                    <p class="stat-number">4.5</p>
                                </div>
                            </div>
                        </div>

                        <div class="recent-activity">
                            <h2>Recent Activity</h2>
                            <div class="activity-list">
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-eye"></i>
                                    </div>
                                    <div class="activity-content">
                                        <p>Your listing "Modern Studio Apartment" received 25 views today</p>
                                        <span class="activity-time">2 hours ago</span>
                                    </div>
                                </div>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="activity-content">
                                        <p>New message from Sarah Johnson regarding "Cozy Studio Near Campus"</p>
                                        <span class="activity-time">5 hours ago</span>
                                    </div>
                                </div>
                                <div class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-star"></i>
                                    </div>
                                    <div class="activity-content">
                                        <p>You received a new 5-star review for "Modern Studio Apartment"</p>
                                        <span class="activity-time">1 day ago</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- My Listings Section -->
                    <?php if ($user_type == 'owner'): ?>
                    <div class="profile-section" id="my-listings">
                        <div class="section-header">
                            <h1>My Listings</h1>
                            <button class="btn btn-primary" onclick="showAddPropertyForm()">
                                <i class="fas fa-plus"></i>
                                Add New Listing
                            </button>
                        </div>
                        <div class="listings-grid">
                            <?php if (isset($listings) && !empty($listings)): ?>
                                <?php foreach ($listings as $property): ?>
                                <!-- Property Card -->
                                <div class="property-card">
                                    <div class="property-image">
                                        <?php if (!empty($property['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($property['image_url']); ?>" alt="<?php echo htmlspecialchars($property['house_title']); ?>">
                                        <?php else: ?>
                                        <img src="https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="<?php echo htmlspecialchars($property['house_title']); ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="property-details">
                                        <h3><?php echo htmlspecialchars($property['house_title']); ?></h3>
                                        <p class="property-price">$<?php echo htmlspecialchars($property['house_price']); ?>/month</p>
                                        <p class="property-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($property['house_location']); ?>, <?php echo htmlspecialchars($property['city_name']); ?></p>
                                        <div class="property-features">
                                            <span><i class="fas fa-bed"></i> <?php echo htmlspecialchars($property['house_badroom']); ?> Bed<?php echo $property['house_badroom'] > 1 ? 's' : ''; ?></span>
                                            <span><i class="fas fa-bath"></i> <?php echo htmlspecialchars($property['house_bathroom']); ?> Bath<?php echo $property['house_bathroom'] > 1 ? 's' : ''; ?></span>
                                            <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($property['proprety_type_name']); ?></span>
                                        </div>
                                        <div class="property-actions">
                                            <a href="#" class="btn btn-sm btn-outline">Edit</a>
                                            <a href="#" class="btn btn-sm btn-danger">Delete</a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-home"></i>
                                    <h3>No Properties Listed</h3>
                                    <p>You haven't listed any properties yet. Add your first property to start attracting student tenants.</p>
                                    <button class="btn btn-primary" onclick="showAddPropertyForm()">Add Your First Property</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Favorites Section -->
                    <?php if ($user_type == 'student'): ?>
                    <div class="profile-section" id="favorites">
                        <h1>My Favorites</h1>
                        <div class="favorites-grid">
                            <?php if (isset($favorites) && !empty($favorites)): ?>
                                <?php foreach ($favorites as $property): ?>
                                <!-- Favorite Property Card -->
                                <div class="property-card">
                                    <div class="property-image">
                                        <?php if (!empty($property['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($property['image_url']); ?>" alt="<?php echo htmlspecialchars($property['house_title']); ?>">
                                        <?php else: ?>
                                        <img src="https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="<?php echo htmlspecialchars($property['house_title']); ?>">
                                        <?php endif; ?>
                                    </div>
                                    <div class="property-details">
                                        <h3><?php echo htmlspecialchars($property['house_title']); ?></h3>
                                        <p class="property-price">$<?php echo htmlspecialchars($property['house_price']); ?>/month</p>
                                        <p class="property-location"><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($property['house_location']); ?>, <?php echo htmlspecialchars($property['city_name']); ?></p>
                                        <div class="property-features">
                                            <span><i class="fas fa-bed"></i> <?php echo htmlspecialchars($property['house_badroom']); ?> Bed<?php echo $property['house_badroom'] > 1 ? 's' : ''; ?></span>
                                            <span><i class="fas fa-bath"></i> <?php echo htmlspecialchars($property['house_bathroom']); ?> Bath<?php echo $property['house_bathroom'] > 1 ? 's' : ''; ?></span>
                                            <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($property['proprety_type_name']); ?></span>
                                        </div>
                                        <div class="property-actions">
                                            <a href="property.php?id=<?php echo $property['house_id']; ?>" class="btn btn-sm btn-outline">View Details</a>
                                            <a href="remove_favorite.php?id=<?php echo $property['house_id']; ?>" class="btn btn-sm btn-danger">Remove</a>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-heart"></i>
                                    <h3>No Favorites Yet</h3>
                                    <p>You haven't saved any properties to your favorites yet.</p>
                                    <a href="offers.php" class="btn btn-primary">Browse Properties</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Add Property Form (for owners) -->
                    <?php if ($user_type == 'owner'): ?>
                    <div class="profile-section" id="add-property" style="display: none;">
                        <h1>Add New Property</h1>
                        <form action="Profile.php" method="POST" enctype="multipart/form-data" class="property-form">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="title">Property Title</label>
                                    <input type="text" id="title" name="title" required placeholder="e.g. Modern Studio Apartment Near Campus">
                                </div>
                                <div class="form-group">
                                    <label for="price">Monthly Rent ($)</label>
                                    <input type="number" id="price" name="price" required placeholder="e.g. 800">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="location">Address</label>
                                    <input type="text" id="location" name="location" required placeholder="e.g. 123 University Ave">
                                </div>
                                <div class="form-group">
                                    <label for="city">City</label>
                                    <select id="city" name="city" required>
                                        <option value="">Select City</option>
                                        <?php
                                        if (isset($cities) && !empty($cities)) {
                                            foreach ($cities as $city) {
                                                echo '<option value="' . $city['city_id'] . '">' . htmlspecialchars($city['city_name']) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="property_type">Property Type</label>
                                    <select id="property_type" name="property_type" required>
                                        <option value="">Select Property Type</option>
                                        <?php
                                        if (isset($property_types) && !empty($property_types)) {
                                            foreach ($property_types as $type) {
                                                echo '<option value="' . $type['proprety_type_id'] . '">' . htmlspecialchars($type['proprety_type_name']) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="bedrooms">Bedrooms</label>
                                    <select id="bedrooms" name="bedrooms" required>
                                        <option value="0">Studio</option>
                                        <option value="1">1 Bedroom</option>
                                        <option value="2">2 Bedrooms</option>
                                        <option value="3">3 Bedrooms</option>
                                        <option value="4">4 Bedrooms</option>
                                        <option value="5">5+ Bedrooms</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="bathrooms">Bathrooms</label>
                                    <select id="bathrooms" name="bathrooms" required>
                                        <option value="1">1 Bathroom</option>
                                        <option value="2">2 Bathrooms</option>
                                        <option value="3">3 Bathrooms</option>
                                        <option value="4">4+ Bathrooms</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group" style="flex: 100%;">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" rows="5" required placeholder="Describe your property, including amenities, distance to campus, etc."></textarea>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group" style="flex: 100%;">
                                    <label for="property_images">Property Images</label>
                                    <input type="file" id="property_images" name="property_images[]" multiple accept="image/*">
                                    <p class="form-help">Upload up to 5 images. Recommended size: 1200x800 pixels.</p>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <input type="hidden" name="submit_property" value="1">
                                <button type="button" class="btn btn-outline" onclick="hideAddPropertyForm()">Cancel</button>
                                <button type="submit" class="btn btn-primary">Submit Property</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>

                    <!-- Messages Section -->
                    <div class="profile-section" id="messages">
                        <h1>Messages</h1>
                        <div class="messages-container">
                            <div class="messages-sidebar">
                                <div class="search-messages">
                                    <input type="text" placeholder="Search messages...">
                                    <i class="fas fa-search"></i>
                                </div>
                                <div class="message-list">
                                    <div class="message-item active">
                                        <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80" alt="Sarah Johnson">
                                        <div class="message-info">
                                            <h4>Sarah Johnson</h4>
                                            <p>Hi, I'm interested in your Modern Studio Apartment...</p>
                                            <span class="message-time">2 hours ago</span>
                                        </div>
                                    </div>
                                    <!-- Add more message items as needed -->
                                </div>
                            </div>
                            <div class="message-content">
                                <div class="message-header">
                                    <div class="user-info">
                                        <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80" alt="Sarah Johnson">
                                        <div>
                                            <h3>Sarah Johnson</h3>
                                            <p>Stanford University Student</p>
                                        </div>
                                    </div>
                                    <div class="message-actions">
                                        <button class="btn btn-outline">
                                            <i class="fas fa-phone"></i>
                                        </button>
                                        <button class="btn btn-outline">
                                            <i class="fas fa-video"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="message-thread">
                                    <!-- Message bubbles will be added here -->
                                </div>
                                <div class="message-input">
                                    <input type="text" placeholder="Type your message...">
                                    <button class="btn btn-primary">
                                        <i class="fas fa-paper-plane"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Settings Section -->
                    <div class="profile-section" id="settings">
                        <h1>Account Settings</h1>
                        <div class="settings-grid">
                            <div class="settings-section">
                                <h2>Profile Information</h2>
                                <form class="settings-form" action="Profile.php" method="POST" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <label for="name">Full Name</label>
                                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user_name); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="email">Email Address</label>
                                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_email); ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="avatar">Profile Picture</label>
                                        <input type="file" id="avatar" name="avatar" accept="image/*">
                                        <p class="form-help">Upload a square image for best results. Max size: 2MB.</p>
                                    </div>
                                    <input type="hidden" name="update_profile" value="1">
                                    <button type="submit" class="btn btn-primary">Save Changes</button>
                                </form>
                            </div>
                            
                            <div class="settings-section">
                                <h2>Change Password</h2>
                                <form class="settings-form" action="Profile.php" method="POST">
                                    <div class="form-group">
                                        <label for="current-password">Current Password</label>
                                        <input type="password" id="current-password" name="current_password" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="new-password">New Password</label>
                                        <input type="password" id="new-password" name="new_password" required>
                                        <p class="form-help">Password must be at least 6 characters long.</p>
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm-password">Confirm New Password</label>
                                        <input type="password" id="confirm-password" name="confirm_password" required>
                                    </div>
                                    <input type="hidden" name="update_profile" value="1">
                                    <input type="hidden" name="name" value="<?php echo htmlspecialchars($user_name); ?>">
                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($user_email); ?>">
                                    <button type="submit" class="btn btn-primary">Update Password</button>
                                </form>
                            </div>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox" checked>
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                    <div class="notification-item">
                                        <div class="notification-info">
                                            <h3>SMS Notifications</h3>
                                            <p>Get instant alerts about new messages</p>
                                        </div>
                                        <label class="switch">
                                            <input type="checkbox">
                                            <span class="slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
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
    <script>
        // Show/hide property form
        function showAddPropertyForm() {
            document.getElementById('add-property').style.display = 'block';
            // Hide other sections
            const sections = document.querySelectorAll('.profile-section');
            sections.forEach(section => {
                if (section.id !== 'add-property') {
                    section.style.display = 'none';
                }
            });
            // Update active tab
            const tabs = document.querySelectorAll('.profile-nav a');
            tabs.forEach(tab => {
                tab.classList.remove('active');
                if (tab.getAttribute('data-section') === 'add-property') {
                    tab.classList.add('active');
                }
            });
        }
        
        function hideAddPropertyForm() {
            document.getElementById('add-property').style.display = 'none';
            // Show dashboard by default
            document.getElementById('dashboard').style.display = 'block';
            // Update active tab
            const tabs = document.querySelectorAll('.profile-nav a');
            tabs.forEach(tab => {
                tab.classList.remove('active');
                if (tab.getAttribute('data-section') === 'dashboard') {
                    tab.classList.add('active');
                }
            });
        }
        
        // Tab navigation
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('.profile-nav a');
            const sections = document.querySelectorAll('.profile-section');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const targetSection = this.getAttribute('data-section');
                    
                    // Hide all sections
                    sections.forEach(section => {
                        section.style.display = 'none';
                    });
                    
                    // Show target section
                    document.getElementById(targetSection).style.display = 'block';
                    
                    // Update active tab
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                });
            });
            
            // Show active section on page load
            const activeTab = document.querySelector('.profile-nav a.active');
            if (activeTab) {
                const targetSection = activeTab.getAttribute('data-section');
                sections.forEach(section => {
                    section.style.display = section.id === targetSection ? 'block' : 'none';
                });
            }
        });
    </script>
</body>
</html> 