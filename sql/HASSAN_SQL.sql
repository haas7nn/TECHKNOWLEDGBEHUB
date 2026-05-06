-- ============================================================================
-- TechKnowledge Hub
-- Course: IT8415 - Database Programming 2
-- Hasan Fardan - 202301686
-- ============================================================================

-- Drop database if exists -- be careful with this
DROP DATABASE IF EXISTS techknowledge_hub;

-- Create database with proper character set
CREATE DATABASE techknowledge_hub 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE techknowledge_hub;

-- ============================================================================
-- TABLE STRUCTURES
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Table 1: techknow_users
-- Stores user accounts with authentication and profile information
-- ----------------------------------------------------------------------------
CREATE TABLE techknow_users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('viewer', 'creator', 'admin') DEFAULT 'viewer' NOT NULL,
    profile_picture VARCHAR(255) NULL,
    bio TEXT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active' NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL,
    
    -- Indexes for performance
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 2: techknow_categories
-- Stores tutorial categories (Web Dev, Database and everything else needed)
-- ----------------------------------------------------------------------------
CREATE TABLE techknow_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT NULL,
    icon VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_category_name (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 3: techknow_tutorials
-- Main content table storing all tutorial information
-- ----------------------------------------------------------------------------
CREATE TABLE techknow_tutorials (
    tutorial_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    short_description VARCHAR(300) NOT NULL,
    content LONGTEXT NOT NULL,
    thumbnail VARCHAR(255) NULL,
    video_url VARCHAR(500) NULL,
    category_id INT NOT NULL,
    instructor_id INT NOT NULL,
    difficulty ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner' NOT NULL,
    duration_minutes INT NULL,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft' NOT NULL,
    view_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at DATETIME NULL,
    
    -- Foreign Keys
    FOREIGN KEY (category_id) REFERENCES techknow_categories(category_id) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    FOREIGN KEY (instructor_id) REFERENCES techknow_users(user_id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Full-text search index (all of it)
    FULLTEXT INDEX ft_search (title, content),
    
    -- Performance indexes
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_category (category_id),
    INDEX idx_instructor (instructor_id),
    INDEX idx_published_at (published_at),
    INDEX idx_difficulty (difficulty),
    INDEX idx_view_count (view_count)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 4: techknow_tutorial_media
-- Stores uploaded files (images, videos, PDFs) for tutorials
-- ----------------------------------------------------------------------------
CREATE TABLE techknow_tutorial_media (
    media_id INT AUTO_INCREMENT PRIMARY KEY,
    tutorial_id INT NOT NULL,
    media_type ENUM('image', 'video', 'document') NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NULL COMMENT 'Size in KB',
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign Key
    FOREIGN KEY (tutorial_id) REFERENCES techknow_tutorials(tutorial_id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Indexes
    INDEX idx_tutorial (tutorial_id),
    INDEX idx_media_type (media_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 5: techknow_ratings
-- Stores user ratings for tutorials (1-5 stars)
-- ----------------------------------------------------------------------------
CREATE TABLE techknow_ratings (
    rating_id INT AUTO_INCREMENT PRIMARY KEY,
    tutorial_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    rated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (tutorial_id) REFERENCES techknow_tutorials(tutorial_id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES techknow_users(user_id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Unique constraint: one rating per user per tutorial
    UNIQUE KEY unique_user_tutorial_rating (tutorial_id, user_id),
    
    -- Indexes
    INDEX idx_tutorial (tutorial_id),
    INDEX idx_user (user_id),
    INDEX idx_rating (rating)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 6: techknow_comments
-- Stores user comments with support for nested replies
-- ----------------------------------------------------------------------------
CREATE TABLE techknow_comments (
    comment_id INT AUTO_INCREMENT PRIMARY KEY,
    tutorial_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_comment_id INT NULL,
    comment_text TEXT NOT NULL,
    status ENUM('approved', 'removed') DEFAULT 'approved' NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (tutorial_id) REFERENCES techknow_tutorials(tutorial_id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (user_id) REFERENCES techknow_users(user_id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (parent_comment_id) REFERENCES techknow_comments(comment_id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Indexes
    INDEX idx_tutorial (tutorial_id),
    INDEX idx_user (user_id),
    INDEX idx_parent (parent_comment_id),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 7: techknow_tags
-- Stores tags for categorizing tutorials (for example "php", "mysql", "beginner")
-- ----------------------------------------------------------------------------
CREATE TABLE techknow_tags (
    tag_id INT AUTO_INCREMENT PRIMARY KEY,
    tag_name VARCHAR(50) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_tag_name (tag_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 8: techknow_tutorial_tags
-- Junction table for many-to-many relationship between tutorials and tags
-- ----------------------------------------------------------------------------
CREATE TABLE techknow_tutorial_tags (
    tutorial_id INT NOT NULL,
    tag_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Composite Primary Key
    PRIMARY KEY (tutorial_id, tag_id),
    
    -- Foreign Keys
    FOREIGN KEY (tutorial_id) REFERENCES techknow_tutorials(tutorial_id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (tag_id) REFERENCES techknow_tags(tag_id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Indexes (automatic on FK but explicit for clarity)
    INDEX idx_tutorial (tutorial_id),
    INDEX idx_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Table 9: techknow_user_activity
-- Tracks user viewing and completion of tutorials
-- ----------------------------------------------------------------------------
CREATE TABLE techknow_user_activity (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tutorial_id INT NOT NULL,
    activity_type ENUM('view', 'complete') NOT NULL,
    activity_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (user_id) REFERENCES techknow_users(user_id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (tutorial_id) REFERENCES techknow_tutorials(tutorial_id) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    
    -- Indexes
    INDEX idx_user (user_id),
    INDEX idx_tutorial (tutorial_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_activity_date (activity_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TRIGGERS
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Trigger 1: UpdateViewCount
-- Automatically updates tutorial view_count when user views it
-- ----------------------------------------------------------------------------
DELIMITER //

CREATE TRIGGER UpdateViewCount
AFTER INSERT ON techknow_user_activity
FOR EACH ROW
BEGIN
    IF NEW.activity_type = 'view' THEN
        UPDATE techknow_tutorials 
        SET view_count = view_count + 1 
        WHERE tutorial_id = NEW.tutorial_id;
    END IF;
END //

DELIMITER ;

-- ----------------------------------------------------------------------------
-- Trigger 2: SetPublishedDate
-- Automatically set published_at timestamp when tutorial status changes to published
-- ----------------------------------------------------------------------------
DELIMITER //

CREATE TRIGGER SetPublishedDate
BEFORE UPDATE ON techknow_tutorials
FOR EACH ROW
BEGIN
    IF OLD.status != 'published' AND NEW.status = 'published' THEN
        SET NEW.published_at = NOW();
    END IF;
END //

DELIMITER ;

-- ============================================================================
-- STORED PROCEDURES
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Procedure 1: GetPopularTutorials
-- Returns most popular tutorials within a date range
-- Parameters:
--   startDate: Beginning of date range
--   endDate: End of date range
--   limitCount: Maximum number of results
-- ----------------------------------------------------------------------------
DELIMITER //

CREATE PROCEDURE GetPopularTutorials(
    IN startDate DATE,
    IN endDate DATE,
    IN limitCount INT
)
BEGIN
    SELECT 
        t.tutorial_id,
        t.title,
        t.slug,
        t.short_description,
        t.thumbnail,
        t.view_count,
        t.difficulty,
        t.duration_minutes,
        t.published_at,
        u.full_name AS instructor_name,
        u.user_id AS instructor_id,
        cat.category_name,
        cat.category_id,
        COUNT(DISTINCT r.rating_id) AS rating_count,
        COALESCE(AVG(r.rating), 0) AS average_rating,
        COUNT(DISTINCT c.comment_id) AS comment_count
    FROM techknow_tutorials t
    INNER JOIN techknow_users u ON t.instructor_id = u.user_id
    INNER JOIN techknow_categories cat ON t.category_id = cat.category_id
    LEFT JOIN techknow_ratings r ON t.tutorial_id = r.tutorial_id
    LEFT JOIN techknow_comments c ON t.tutorial_id = c.comment_id AND c.status = 'approved'
    WHERE t.status = 'published'
    AND DATE(t.published_at) BETWEEN startDate AND endDate
    GROUP BY t.tutorial_id
    ORDER BY t.view_count DESC, average_rating DESC
    LIMIT limitCount;
END //

DELIMITER ;

-- ----------------------------------------------------------------------------
-- Procedure 2: GetInstructorReport
-- Returns comprehensive performance report for an instructor
-- Parameters:
--   instructorId: ID of the instructor
-- Returns: Two result sets
--   1. Summary statistics
--   2. Individual tutorial performance
-- ----------------------------------------------------------------------------
DELIMITER //

CREATE PROCEDURE GetInstructorReport(IN instructorId INT)
BEGIN
    -- Result Set 1: Summary Statistics
    SELECT 
        u.user_id,
        u.full_name,
        u.email,
        u.bio,
        u.created_at AS member_since,
        COUNT(DISTINCT t.tutorial_id) AS total_tutorials,
        SUM(t.view_count) AS total_views,
        COALESCE(AVG(r.rating), 0) AS average_rating,
        COUNT(DISTINCT r.rating_id) AS total_ratings,
        COUNT(DISTINCT c.comment_id) AS total_comments,
        COUNT(DISTINCT CASE WHEN t.status = 'published' THEN t.tutorial_id END) AS published_tutorials,
        COUNT(DISTINCT CASE WHEN t.status = 'draft' THEN t.tutorial_id END) AS draft_tutorials
    FROM techknow_users u
    LEFT JOIN techknow_tutorials t ON u.user_id = t.instructor_id
    LEFT JOIN techknow_ratings r ON t.tutorial_id = r.tutorial_id
    LEFT JOIN techknow_comments c ON t.tutorial_id = c.comment_id AND c.status = 'approved'
    WHERE u.user_id = instructorId
    GROUP BY u.user_id;
    
    -- Result Set 2: Individual Tutorial Performance
    SELECT 
        t.tutorial_id,
        t.title,
        t.slug,
        t.status,
        t.difficulty,
        t.view_count,
        t.published_at,
        cat.category_name,
        COUNT(DISTINCT r.rating_id) AS rating_count,
        COALESCE(AVG(r.rating), 0) AS average_rating,
        COUNT(DISTINCT c.comment_id) AS comment_count
    FROM techknow_tutorials t
    INNER JOIN techknow_categories cat ON t.category_id = cat.category_id
    LEFT JOIN techknow_ratings r ON t.tutorial_id = r.tutorial_id
    LEFT JOIN techknow_comments c ON t.tutorial_id = c.comment_id AND c.status = 'approved'
    WHERE t.instructor_id = instructorId
    GROUP BY t.tutorial_id
    ORDER BY t.published_at DESC;
END //

DELIMITER ;

-- ============================================================================
-- SAMPLE DATA
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Insert Categories (6 categories)
-- ----------------------------------------------------------------------------
INSERT INTO techknow_categories (category_name, description, icon) VALUES
('Web Development', 'Learn HTML, CSS, JavaScript and modern web frameworks', 'fa-code'),
('Database Management', 'Master SQL, MySQL, MongoDB and database design principles', 'fa-database'),
('Programming', 'Python, Java, C++ and programming fundamentals', 'fa-laptop-code'),
('Mobile Development', 'iOS, Android, React Native and cross-platform development', 'fa-mobile-alt'),
('Artificial Intelligence', 'Machine Learning, Deep Learning, and AI concepts', 'fa-brain'),
('Cloud Computing', 'AWS, Azure, Google Cloud platforms and DevOps', 'fa-cloud');

-- ----------------------------------------------------------------------------
-- Insert Users (6 users)
-- All passwords are: Password123!
-- Hashed using bcrypt: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- ----------------------------------------------------------------------------
INSERT INTO techknow_users (full_name, email, password_hash, role, bio, status) VALUES
('John Admin', 'admin@techknow.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator with 10+ years experience in education technology', 'active'),
('Sarah Johnson', 'sarah.j@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'creator', 'Full Stack Developer & Instructor specializing in web technologies. Passionate about making programming accessible to everyone.', 'active'),
('Mike Chen', 'mike.chen@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'creator', 'Database Expert and SQL Specialist with industry certifications. Former DBA at Fortune 500 companies.', 'active'),
('Emily Davis', 'emily.d@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'creator', 'Python Developer & AI Enthusiast. Teaching machine learning and data science to beginners.', 'active'),
('David Wilson', 'david.w@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'viewer', 'Computer Science student learning web development and databases', 'active'),
('Lisa Brown', 'lisa.b@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'viewer', 'Aspiring software engineer interested in full-stack development', 'active');

-- ----------------------------------------------------------------------------
-- Insert Tutorials (15 tutorials)
-- ----------------------------------------------------------------------------
INSERT INTO techknow_tutorials (title, slug, short_description, content, thumbnail, video_url, category_id, instructor_id, difficulty, duration_minutes, status, view_count, published_at) VALUES
-- Tutorial 1
('Complete PHP & MySQL Course for Beginners', 'php-mysql-beginners', 'Learn PHP and MySQL from scratch with hands-on projects and real-world examples', 
'<h2>Introduction to PHP</h2><p>PHP is a powerful server-side scripting language designed for web development. In this comprehensive course, you will learn everything from basic syntax to advanced database integration.</p><h2>What You Will Learn</h2><ul><li>PHP syntax and fundamentals</li><li>Working with forms and user input</li><li>MySQL database integration</li><li>CRUD operations</li><li>Security best practices</li></ul><h2>Course Content</h2><p>We start with the basics of PHP syntax, variables, and control structures. Then we move into functions, arrays, and object-oriented programming. Finally, we integrate MySQL databases and build a complete web application.</p>', 
'php-mysql-thumb.jpg', 'https://example.com/videos/php-mysql-course.mp4', 1, 2, 'beginner', 240, 'published', 1523, '2024-01-15 10:00:00'),

-- Tutorial 2
('Advanced JavaScript ES6+ Features', 'javascript-es6-advanced', 'Master modern JavaScript with ES6+ features including async/await, modules, and more', 
'<h2>Modern JavaScript</h2><p>JavaScript has evolved tremendously. This course covers all the latest ES6+ features that every developer should know.</p><h2>Topics Covered</h2><ul><li>Arrow functions and lexical this</li><li>Destructuring and spread operators</li><li>Promises and async/await</li><li>ES6 modules</li><li>Classes and inheritance</li></ul><h2>Practical Applications</h2><p>Learn how to write cleaner, more efficient code using modern JavaScript syntax. Build real-world applications using async programming patterns.</p>', 
'js-es6-thumb.jpg', 'https://example.com/videos/js-es6.mp4', 1, 2, 'advanced', 180, 'published', 2340, '2024-01-20 14:30:00'),

-- Tutorial 3
('Database Design Fundamentals', 'database-design-fundamentals', 'Learn how to design efficient and scalable databases with normalization and best practices', 
'<h2>Database Design Principles</h2><p>Good database design is the foundation of any successful application. This tutorial teaches you how to create efficient, scalable database schemas.</p><h2>Key Concepts</h2><ul><li>Entity Relationship Diagrams (ERD)</li><li>Normalization (1NF, 2NF, 3NF)</li><li>Primary and foreign keys</li><li>Indexing strategies</li><li>Data integrity</li></ul><h2>Hands-On Practice</h2><p>Work through real-world scenarios and learn to avoid common database design pitfalls.</p>', 
'db-design-thumb.jpg', NULL, 2, 3, 'beginner', 150, 'published', 1876, '2024-02-01 09:00:00'),

-- Tutorial 4
('SQL Query Optimization Techniques', 'sql-optimization', 'Speed up your database queries with indexing, query optimization, and performance tuning', 
'<h2>Database Performance</h2><p>Learn advanced techniques to make your SQL queries lightning fast.</p><h2>Topics</h2><ul><li>Understanding EXPLAIN plans</li><li>Index types and strategies</li><li>Query rewriting techniques</li><li>Join optimization</li><li>Caching strategies</li></ul><h2>Real-World Examples</h2><p>See how to optimize real queries from slow to fast with concrete examples.</p>', 
'sql-opt-thumb.jpg', 'https://example.com/videos/sql-optimization.mp4', 2, 3, 'intermediate', 120, 'published', 2890, '2024-02-10 11:00:00'),

-- Tutorial 5
('Python for Data Science', 'python-data-science', 'Use Python libraries like Pandas, NumPy, and Matplotlib for data analysis and visualization', 
'<h2>Data Science with Python</h2><p>Python is the go-to language for data science. Learn the essential libraries and techniques.</p><h2>Libraries Covered</h2><ul><li>NumPy for numerical computing</li><li>Pandas for data manipulation</li><li>Matplotlib for visualization</li><li>Data cleaning and preprocessing</li></ul><h2>Projects</h2><p>Build real data analysis projects and create stunning visualizations.</p>', 
'python-ds-thumb.jpg', 'https://example.com/videos/python-data-science.mp4', 3, 4, 'intermediate', 200, 'published', 3456, '2024-02-15 13:00:00'),

-- Tutorial 6
('Machine Learning with Python', 'machine-learning-python', 'Build your first machine learning models using scikit-learn and TensorFlow', 
'<h2>Introduction to Machine Learning</h2><p>Start your journey into AI and machine learning with practical, hands-on examples.</p><h2>What You Will Build</h2><ul><li>Classification models</li><li>Regression algorithms</li><li>Neural networks basics</li><li>Model evaluation techniques</li></ul><h2>Prerequisites</h2><p>Basic Python knowledge required. We will guide you through all ML concepts from scratch.</p>', 
'ml-python-thumb.jpg', 'https://example.com/videos/machine-learning.mp4', 5, 4, 'advanced', 300, 'published', 4123, '2024-03-01 10:00:00'),

-- Tutorial 7
('React Native Mobile App Development', 'react-native-mobile', 'Build cross-platform mobile apps with React Native for iOS and Android', 
'<h2>Mobile Development Made Easy</h2><p>Learn to build professional mobile apps using JavaScript and React Native.</p><h2>Course Outline</h2><ul><li>React Native setup</li><li>Components and styling</li><li>Navigation patterns</li><li>API integration</li><li>Publishing to app stores</li></ul><h2>Build Real Apps</h2><p>Create actual mobile applications that run on both iOS and Android devices.</p>', 
'react-native-thumb.jpg', NULL, 4, 2, 'intermediate', 250, 'published', 2567, '2024-03-10 15:00:00'),

-- Tutorial 8
('AWS Cloud Essentials', 'aws-cloud-essentials', 'Get started with Amazon Web Services including EC2, S3, and RDS', 
'<h2>Cloud Computing with AWS</h2><p>Master the fundamentals of cloud computing using Amazon Web Services.</p><h2>Services Covered</h2><ul><li>EC2 - Virtual servers</li><li>S3 - Object storage</li><li>RDS - Managed databases</li><li>Lambda - Serverless computing</li><li>IAM - Security and access</li></ul><h2>Hands-On Labs</h2><p>Deploy real applications to AWS cloud infrastructure.</p>', 
'aws-thumb.jpg', 'https://example.com/videos/aws-essentials.mp4', 6, 3, 'beginner', 180, 'published', 3789, '2024-03-20 12:00:00'),

-- Tutorial 9
('RESTful API Design Best Practices', 'restful-api-design', 'Design clean, scalable, and secure REST APIs for your applications', 
'<h2>API Design Principles</h2><p>Learn industry-standard practices for building robust RESTful APIs.</p><h2>Topics</h2><ul><li>REST architecture principles</li><li>HTTP methods and status codes</li><li>Authentication (JWT, OAuth)</li><li>API versioning strategies</li><li>Documentation with Swagger</li></ul><h2>Build Your API</h2><p>Create a production-ready API following best practices.</p>', 
'rest-api-thumb.jpg', 'https://example.com/videos/rest-api.mp4', 1, 2, 'intermediate', 140, 'published', 2145, '2024-03-25 09:30:00'),

-- Tutorial 10
('Git & GitHub for Beginners', 'git-github-beginners', 'Master version control with Git and collaborate using GitHub', 
'<h2>Version Control Essentials</h2><p>Every developer needs to know Git. This tutorial teaches you everything from basics to collaboration.</p><h2>Learn Git</h2><ul><li>Git basics and workflow</li><li>Branching and merging</li><li>Resolving conflicts</li><li>GitHub collaboration</li><li>Pull requests and code reviews</li></ul><h2>Team Workflows</h2><p>Understand how professional teams use Git in real projects.</p>', 
'git-github-thumb.jpg', NULL, 3, 4, 'beginner', 90, 'published', 5234, '2024-04-01 10:00:00'),

-- Tutorial 11
('Building Responsive Websites with CSS Grid', 'css-grid-responsive', 'Create modern, responsive layouts using CSS Grid and Flexbox', 
'<h2>Modern CSS Layouts</h2><p>CSS Grid is a game-changer for web layouts. Learn to build beautiful, responsive designs.</p><h2>What You Will Master</h2><ul><li>CSS Grid fundamentals</li><li>Grid template areas</li><li>Responsive design patterns</li><li>Flexbox integration</li><li>Real-world layouts</li></ul><h2>Projects</h2><p>Build portfolio sites, dashboards, and magazine-style layouts.</p>', 
'css-grid-thumb.jpg', 'https://example.com/videos/css-grid.mp4', 1, 2, 'beginner', 110, 'published', 1987, '2024-04-05 14:00:00'),

-- Tutorial 12
('Node.js Backend Development', 'nodejs-backend-dev', 'Build scalable backend applications with Node.js and Express', 
'<h2>Server-Side JavaScript</h2><p>Learn to build powerful backend systems using Node.js and Express framework.</p><h2>Course Content</h2><ul><li>Node.js fundamentals</li><li>Express.js framework</li><li>RESTful API development</li><li>Database integration</li><li>Authentication and security</li></ul><h2>Full-Stack JavaScript</h2><p>Use JavaScript on both frontend and backend for complete applications.</p>', 
'nodejs-thumb.jpg', 'https://example.com/videos/nodejs-backend.mp4', 1, 3, 'intermediate', 220, 'published', 3012, '2024-04-10 11:00:00'),

-- Tutorial 13
('Docker Containerization for Developers', 'docker-containerization', 'Learn Docker to containerize and deploy your applications efficiently', 
'<h2>Containerization Made Simple</h2><p>Docker revolutionizes how we deploy applications. Learn container technology from scratch.</p><h2>Topics</h2><ul><li>Docker basics and architecture</li><li>Creating Dockerfiles</li><li>Docker Compose</li><li>Container orchestration</li><li>Deployment strategies</li></ul><h2>DevOps Skills</h2><p>Gain essential DevOps skills that are in high demand in the industry.</p>', 
'docker-thumb.jpg', NULL, 6, 3, 'intermediate', 160, 'published', 2678, '2024-04-15 13:00:00'),

-- Tutorial 14
('Introduction to MongoDB', 'intro-mongodb', 'Get started with NoSQL databases using MongoDB', 
'<h2>NoSQL with MongoDB</h2><p>Learn the most popular NoSQL database and when to use it over traditional SQL databases.</p><h2>Learn MongoDB</h2><ul><li>NoSQL concepts</li><li>Document-based storage</li><li>CRUD operations</li><li>Aggregation framework</li><li>Indexing and performance</li></ul><h2>Build Applications</h2><p>Integrate MongoDB with Node.js and build modern web applications.</p>', 
'mongodb-thumb.jpg', 'https://example.com/videos/mongodb-intro.mp4', 2, 3, 'beginner', 130, 'published', 2234, '2024-04-20 10:30:00'),

-- Tutorial 15
('Vue.js 3 Complete Guide', 'vuejs-3-guide', 'Build modern web applications with Vue.js 3 and the Composition API', 
'<h2>Modern Frontend Framework</h2><p>Vue.js 3 brings powerful features and improved performance. Learn the latest version.</p><h2>Course Outline</h2><ul><li>Vue 3 fundamentals</li><li>Composition API</li><li>Component communication</li><li>State management with Pinia</li><li>Vue Router</li></ul><h2>Build Real Apps</h2><p>Create interactive single-page applications with Vue.js 3.</p>', 
'vuejs-thumb.jpg', 'https://example.com/videos/vuejs-3.mp4', 1, 2, 'intermediate', 195, 'published', 1823, '2024-04-25 15:00:00');

-- ----------------------------------------------------------------------------
-- Insert Tags (25 tags)
-- ----------------------------------------------------------------------------
INSERT INTO techknow_tags (tag_name) VALUES
('php'), ('mysql'), ('javascript'), ('es6'), ('database'), 
('sql'), ('python'), ('data-science'), ('machine-learning'), 
('react-native'), ('mobile'), ('aws'), ('cloud'), ('rest-api'), 
('git'), ('github'), ('css'), ('responsive'), ('nodejs'), 
('docker'), ('mongodb'), ('nosql'), ('vuejs'), ('backend'), ('frontend');

-- ----------------------------------------------------------------------------
-- Insert Tutorial Tags (associating tutorials with relevant tags)
-- ----------------------------------------------------------------------------
INSERT INTO techknow_tutorial_tags (tutorial_id, tag_id) VALUES
-- Tutorial 1: PHP & MySQL
(1, 1), (1, 2), (1, 24),
-- Tutorial 2: JavaScript ES6
(2, 3), (2, 4), (2, 25),
-- Tutorial 3: Database Design
(3, 5), (3, 6),
-- Tutorial 4: SQL Optimization
(4, 6), (4, 5),
-- Tutorial 5: Python Data Science
(5, 7), (5, 8),
-- Tutorial 6: Machine Learning
(6, 7), (6, 9),
-- Tutorial 7: React Native
(7, 10), (7, 11), (7, 3),
-- Tutorial 8: AWS
(8, 12), (8, 13),
-- Tutorial 9: REST API
(9, 14), (9, 24),
-- Tutorial 10: Git & GitHub
(10, 15), (10, 16),
-- Tutorial 11: CSS Grid
(11, 17), (11, 18), (11, 25),
-- Tutorial 12: Node.js
(12, 19), (12, 24),
-- Tutorial 13: Docker
(13, 20), (13, 13),
-- Tutorial 14: MongoDB
(14, 21), (14, 22), (14, 5),
-- Tutorial 15: Vue.js
(15, 23), (15, 25);

-- ----------------------------------------------------------------------------
-- Insert Ratings (realistic distribution across tutorials)
-- ----------------------------------------------------------------------------
INSERT INTO techknow_ratings (tutorial_id, user_id, rating) VALUES
-- Tutorial 1
(1, 5, 5), (1, 6, 4),
-- Tutorial 2
(2, 5, 5), (2, 6, 5),
-- Tutorial 3
(3, 5, 4), (3, 6, 5),
-- Tutorial 4
(4, 5, 5), (4, 6, 5),
-- Tutorial 5
(5, 5, 4), (5, 6, 4),
-- Tutorial 6
(6, 5, 5), (6, 6, 5),
-- Tutorial 7
(7, 5, 4), (7, 6, 4),
-- Tutorial 8
(8, 5, 5), (8, 6, 4),
-- Tutorial 9
(9, 5, 4), (9, 6, 5),
-- Tutorial 10
(10, 5, 5), (10, 6, 5),
-- Tutorial 11
(11, 5, 4), (11, 6, 3),
-- Tutorial 12
(12, 5, 5), (12, 6, 4),
-- Tutorial 13
(13, 5, 4), (13, 6, 4),
-- Tutorial 14
(14, 5, 4), (14, 6, 5),
-- Tutorial 15
(15, 5, 3), (15, 6, 4);

-- ----------------------------------------------------------------------------
-- Insert Comments (engaging, realistic comments)
-- ----------------------------------------------------------------------------
INSERT INTO techknow_comments (tutorial_id, user_id, parent_comment_id, comment_text, status) VALUES
-- Tutorial 1
(1, 5, NULL, 'Excellent tutorial! Very clear explanations for beginners. The examples really helped me understand PHP basics.', 'approved'),
(1, 6, 1, 'I completely agree! This helped me understand PHP perfectly. Moving on to the MySQL part now.', 'approved'),
-- Tutorial 2
(2, 5, NULL, 'The async/await section was particularly helpful! Finally understand promises now.', 'approved'),
-- Tutorial 3
(3, 6, NULL, 'Great overview of database normalization. The ERD examples made everything click. Thank you!', 'approved'),
-- Tutorial 4
(4, 5, NULL, 'Indexing examples were spot on. My queries are much faster now. Highly recommend this tutorial!', 'approved'),
-- Tutorial 5
(5, 6, NULL, 'Pandas tutorial was amazing. Looking forward to more data science content from you!', 'approved'),
-- Tutorial 6
(6, 5, NULL, 'This got me started with ML. Very practical examples. The TensorFlow section was especially good.', 'approved'),
-- Tutorial 7
(7, 6, NULL, 'React Native setup was tricky but this guide made it easy. Built my first app successfully!', 'approved'),
-- Tutorial 8
(8, 5, NULL, 'AWS can be overwhelming, but this tutorial breaks it down well. The EC2 examples were perfect.', 'approved'),
-- Tutorial 10
(10, 6, NULL, 'Git finally makes sense! Best beginner tutorial I have found. The branching section was gold.', 'approved'),
-- Tutorial 11
(11, 5, NULL, 'CSS Grid is a game changer. Thanks for the responsive patterns! My layouts look so much better now.', 'approved'),
-- Tutorial 12
(12, 6, NULL, 'Node.js + Express combination is powerful. Great tutorial! Ready to build my own API now.', 'approved');

-- ----------------------------------------------------------------------------
-- Insert User Activity (tracking views and completions)
-- ----------------------------------------------------------------------------
INSERT INTO techknow_user_activity (user_id, tutorial_id, activity_type) VALUES
-- David's activity
(5, 1, 'view'), (5, 1, 'complete'),
(5, 2, 'view'), (5, 3, 'view'),
(5, 4, 'view'), (5, 4, 'complete'),
(5, 5, 'view'), (5, 6, 'view'),
-- Lisa's activity
(6, 1, 'view'), (6, 2, 'view'),
(6, 3, 'view'), (6, 3, 'complete'),
(6, 10, 'view'), (6, 10, 'complete'),
(6, 11, 'view');

-- ----------------------------------------------------------------------------
-- Insert Tutorial Media (sample media files)
-- ----------------------------------------------------------------------------
INSERT INTO techknow_tutorial_media (tutorial_id, media_type, file_name, file_path, file_size) VALUES
(1, 'image', 'php-syntax-example.png', 'uploads/tutorials/1/php-syntax-example.png', 245),
(1, 'document', 'php-cheatsheet.pdf', 'uploads/tutorials/1/php-cheatsheet.pdf', 1240),
(2, 'image', 'es6-features-diagram.png', 'uploads/tutorials/2/es6-features-diagram.png', 312),
(3, 'image', 'erd-example-ecommerce.png', 'uploads/tutorials/3/erd-example-ecommerce.png', 428),
(4, 'document', 'sql-optimization-guide.pdf', 'uploads/tutorials/4/sql-optimization-guide.pdf', 987),
(5, 'image', 'pandas-dataframe-operations.png', 'uploads/tutorials/5/pandas-dataframe-operations.png', 356),
(6, 'image', 'neural-network-architecture.png', 'uploads/tutorials/6/neural-network-architecture.png', 512),
(7, 'image', 'react-native-components.png', 'uploads/tutorials/7/react-native-components.png', 289),
(8, 'image', 'aws-architecture-diagram.png', 'uploads/tutorials/8/aws-architecture-diagram.png', 467),
(9, 'document', 'api-documentation-template.pdf', 'uploads/tutorials/9/api-documentation-template.pdf', 654);

-- ============================================================================
-- VERIFICATION QUERIES (Run these to verify everything is working)
-- ============================================================================

-- Check table counts
SELECT 
    'users' AS table_name, COUNT(*) AS row_count FROM techknow_users
UNION ALL
SELECT 'categories', COUNT(*) FROM techknow_categories
UNION ALL
SELECT 'tutorials', COUNT(*) FROM techknow_tutorials
UNION ALL
SELECT 'media', COUNT(*) FROM techknow_tutorial_media
UNION ALL
SELECT 'ratings', COUNT(*) FROM techknow_ratings
UNION ALL
SELECT 'comments', COUNT(*) FROM techknow_comments
UNION ALL
SELECT 'tags', COUNT(*) FROM techknow_tags
UNION ALL
SELECT 'tutorial_tags', COUNT(*) FROM techknow_tutorial_tags
UNION ALL
SELECT 'user_activity', COUNT(*) FROM techknow_user_activity;

-- Test stored procedure 1
CALL GetPopularTutorials('2024-01-01', '2024-12-31', 5);

-- Test stored procedure 2
CALL GetInstructorReport(2);

-- Test full-text search
SELECT tutorial_id, title, MATCH(title, content) AGAINST('javascript') AS relevance
FROM techknow_tutorials
WHERE MATCH(title, content) AGAINST('javascript')
ORDER BY relevance DESC
LIMIT 5;

-- ============================================================================
-- SETUP COMPLETE
-- ============================================================================

-- Display success message
SELECT 
    '✅ DATABASE SETUP COMPLETE!' AS Status,
    'All tables, triggers, procedures, and sample data loaded successfully.' AS Message,
    'You can now start building your application!' AS NextStep;

-- Display test account information
SELECT 
    'TEST ACCOUNTS' AS Information,
    'All passwords are: Password123!' AS Password;

SELECT 
    full_name AS Name,
    email AS Email,
    role AS Role,
    'Password123!' AS Password
FROM techknow_users
ORDER BY 
    CASE role 
        WHEN 'admin' THEN 1 
        WHEN 'creator' THEN 2 
        WHEN 'viewer' THEN 3 
    END;