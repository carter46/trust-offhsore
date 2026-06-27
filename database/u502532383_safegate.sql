-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jun 24, 2026 at 06:15 PM
-- Server version: 11.8.6-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u502532383_safegate`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `email`, `last_login`, `created_at`) VALUES
(1, 'admin', '$2y$10$esuCnwmisMxWnv90J.zGa.HOdydPkEuEkPtfMjI6tUHoaZSte5UGG', 'admin@courier.com', '2026-01-27 22:26:42', '2026-01-14 19:07:59');

-- --------------------------------------------------------

--
-- Table structure for table `cookie_preferences`
--

CREATE TABLE `cookie_preferences` (
  `id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL,
  `preferences` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pages`
--

CREATE TABLE `pages` (
  `id` int(11) NOT NULL,
  `page_slug` varchar(100) NOT NULL,
  `page_title` varchar(255) NOT NULL,
  `content` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`content`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `pages`
--

INSERT INTO `pages` (`id`, `page_slug`, `page_title`, `content`, `created_at`, `updated_at`) VALUES
(1, 'homepage', 'Homepage', '{\"hero\":{\"bg_image\":\"/asset/hero_bg_1768431656_7091.webp\",\"heading\":\"Ship, manage, track, deliver\",\"tabs\":[{\"text\":\"Rate &amp;amp; Ship\",\"link\":\"#\",\"icon\":\"calculate\"},{\"text\":\"Track\",\"link\":\"#\",\"icon\":\"inventory_2\"},{\"text\":\"Locations\",\"link\":\"#\",\"icon\":\"place\"}],\"track_button_text\":\"Track\"},\"service_icons\":[{\"image\":\"/asset/icon1.png\",\"text\":\"Drop off a\\r\\nPackage\",\"link\":\"#\"},{\"image\":\"/asset/icon2.png\",\"text\":\"Redirect a\\r\\nPackage\",\"link\":\"#\"},{\"image\":\"/asset/icon3.svg\",\"text\":\"Store Hours\\r\\nand Services\",\"link\":\"#\"},{\"image\":\"/asset/icon4.png\",\"text\":\"Service\\r\\nAlerts\",\"link\":\"#\"},{\"image\":\"/asset/icon5.png\",\"text\":\"Return a\\r\\nPackage\",\"link\":\"#\"}],\"why_ship\":{\"image\":\"/asset/why_ship_1768431656_8151.jpg\",\"heading\":\"Why ship with {company}?\",\"features\":[{\"heading\":\"Smart logistics you can depend on\",\"text\":\"We combine modern tracking technology with experienced handling to ensure your shipments arrive safely and on time—every time.\"},{\"heading\":\"Premium shipping at professional rates\",\"text\":\"When you need reliable delivery and careful handling, trust {company} to get your items where they need to go on time.\"},{\"heading\":\"Global delivery coverage\",\"text\":\"From major cities to remote locations, your goods can reach worldwide.\"},{\"heading\":\"Professional shipping at competitive prices\",\"text\":\"Get dependable delivery without overpaying. Our pricing is designed to support individuals and growing businesses alike.\"}],\"button_text\":\"Start Shipping Now\",\"button_link\":\"#\",\"footer_text\":\"**Exclusions apply. Visit the {company} One Rate page to learn more.\"},\"business_gear\":{\"heading\":\"Solutions for Every Shipping Need\",\"cards\":[{\"image\":\"/asset/business_gear1_1768431656_2181.jpg\",\"heading\":\"Simplify returns for your customers\",\"text\":\"Make returns easier with flexible drop-off options, digital labels, and fast processing—no unnecessary steps.\",\"link_text\":\"View Returns Options\",\"link\":\"#\"},{\"image\":\"/asset/business_gear2_1768431656_9543.jpg\",\"heading\":\"Earn rewards when you ship\",\"text\":\"Join the {company} Rewards Program and earn points for every shipment. Redeem rewards for gift cards and exclusive offers.\",\"link_text\":\"Open a Free Account\",\"link\":\"#\"},{\"image\":\"/asset/business_gear3_1768431656_6463.jpg\",\"heading\":\"Support for international shipping rules\",\"text\":\"Shipping across borders? We help you navigate customs duties, tariffs, and trade regulations with confidence using our compliance tools and expert guidance.\",\"link_text\":\"Understand Tariffs\",\"link\":\"#\"}]},\"shipping_supplies\":{\"image\":\"/asset/shipping_supplies_1768431656_8035.jpg\",\"heading\":\"Packaging supplies for every item\",\"text\":\"Find boxes, mailers, cushioning, and specialty packaging designed to protect your shipments—at prices that make sense.\",\"link_text\":\"Shop Supplies\",\"link\":\"#\"},\"ship_track_return\":{\"heading\":\"Ship, track, receive, and return—all on your terms\",\"items\":[{\"image\":\"/asset/ship_track_return1_1768431656_5847.jpg\",\"heading\":\"Simple shipping is in hand\",\"text\":\"With the {company}® Mobile app, you can manage almost all your shipping tasks right from your phone. Create or track shipments, get QR codes, scan barcodes, see picture proof of delivery, and more. Plus, its free!\",\"link_text\":\"Download the App\",\"link\":\"#\"},{\"image\":\"/asset/ship_track_return2_1768431656_3644.jpg\",\"heading\":\"Decide how and where your shipments arrive\",\"text\":\"Stay in the know and in control. With {company} Delivery Manager®, you get more than notifications. You can tell your driver where to leave your package, or sign for it remotely. You can even redirect it and have someone else pick it up.\",\"link_text\":\"Enroll for Free\",\"link\":\"#\"},{\"image\":\"/asset/ship_track_return3_1768431656_6262.jpg\",\"heading\":\"Why regift when you can return?\",\"text\":\"Got a holiday gift that&amp;#039;s the wrong size or style? Don&amp;#039;t worry! {company} returns are easy. Just start your return with the retailer, then drop it off where it&amp;#039;s most convenient.\",\"link_text\":\"Find Nearby Locations\",\"link\":\"#\"}],\"footer_items\":[{\"heading\":\"{company} rate and surcharge changes\",\"text\":\"Learn more about rate and surcharge changes—last updated {date}.\",\"link\":\"#\"},{\"heading\":\"{company} money-back guarantee\",\"text\":\"We offer a money-back guarantee for select services. This guarantee may be suspended, modified, or revoked. Please check money-back guarantee for the latest status of our money-back guarantee.\",\"link\":\"#\"}],\"footer_disclaimer\":\"*For details, please see {company} Rewards Terms and Conditions.\"}}', '2026-01-14 19:07:59', '2026-01-14 23:06:12'),
(2, 'our-services', 'Our Services', '{\"hero\":{\"heading\":\"Our Services\",\"subtitle\":\"We Provide you with the best services out there!\",\"description\":\"We offer dependable sea freight solutions for clients who need to move goods across international waters. Whether you&#039;re handling full container loads or smaller shipments\",\"track_shipment_text\":\"Track Shipment\",\"track_shipment_link\":\"/track.php\"},\"section_title\":\"What We Offer!\",\"services\":[{\"image\":\"/asset/service1_1768432163_7326.jpg\",\"title\":\"Sea Freight\",\"description\":\"We offer dependable sea freight solutions for clients who need to move goods across international waters. Whether you&#039;re handling full container loads or smaller shipments, our team ensures timely, cost-effective, and compliant ocean shipping from port to port.\",\"key_features\":[\"Full Container Load (FCL) &amp; Less-than-Container Load (LCL)\",\"Bulk &amp; Breakbulk Cargo Handling\",\"Import/Export Documentation &amp; Customs Clearance\",\"Door-to-Port &amp; Door-to-Door Delivery Options\"],\"cta_text\":\"Track Shipment\",\"cta_link\":\"/track.php\"},{\"image\":\"/asset/service2_1768432163_1015.webp\",\"title\":\"Air Freight\",\"description\":\"For urgent shipments, Freightways Logistics offers fast and reliable air freight services that ensure quick delivery across domestic and international routes.\",\"key_features\":[\"Express &amp; Standard Air Freight\",\"Cargo Pickup &amp; Packaging\",\"Cargo Handling &amp; Packaging\",\"Airport-to-Airport &amp; Door-to-Door Services\",\"Real-Time Shipment Tracking\"],\"cta_text\":\"Track Shipment\",\"cta_link\":\"/track.php\"},{\"image\":\"/asset/service3_1768432163_9888.jpg\",\"title\":\"Haulage &amp; Local Transport\",\"description\":\"From cross-state hauls to last-mile delivery within the US and beyond, our modern fleet and experienced drivers provide safe, reliable, and timely ground transport for all types of goods.\",\"key_features\":[\"Nationwide &amp; Regional Trucking\",\"Full Truckload (FTL) &amp; Less-than-Truckload (LTL)\",\"Specialized &amp; Oversized Cargo Handling\",\"Last-Mile Distribution\"],\"cta_text\":\"Track Shipment\",\"cta_link\":\"/track.php\"},{\"image\":\"/asset/service4_1768432163_9240.jpg\",\"title\":\"Bulk Cargo Handling\",\"description\":\"For clients with large-scale or heavy-volume shipments, we offer tailored bulk cargo logistics, including vessel chartering and inland transportation. Our bulk services are designed to reduce cost while maintaining speed and safety.\",\"key_features\":[\"Vessel &amp; Equipment Chartering\",\"Efficient Loading &amp; Unloading\",\"Heavy Cargo Management\",\"Port &amp; Inland Clearance Assistance\"],\"cta_text\":\"Track Shipment\",\"cta_link\":\"/track.php\"},{\"image\":\"/asset/service5_1768432163_1963.jpg\",\"title\":\"Clearing &amp; Customs Brokerage\",\"description\":\"We simplify the customs clearance process for imports and exports, handling documentation, duties, and compliance with U.S. and international trade laws. We ensure your shipments are processed swiftly and legally.\",\"key_features\":[\"U.S. Customs Brokerage Services\",\"Regulatory Documentation Handling\",\"Duty Classification &amp; Processing\",\"Port Coordination &amp; Cargo Release\"],\"cta_text\":\"Track Shipment\",\"cta_link\":\"/track.php\"},{\"image\":\"/asset/service6_1768432163_7551.jpg\",\"title\":\"Import &amp; Duty Finance\",\"description\":\"Importing can be expensive — that&#039;s why we offer flexible financial solutions to help you manage the upfront costs of duties and taxes. Our import duty financing helps reduce cash flow strain while keeping your shipments moving.\",\"key_features\":[\"Duty &amp; Tax Payment Support\",\"Structured Repayment Plans\",\"Full Logistics Coordination\",\"Support for High-Volume Importers\"],\"cta_text\":\"Track Shipment\",\"cta_link\":\"/track.php\"},{\"image\":\"/asset/service7_1768432163_2040.jpg\",\"title\":\"Courier Services\",\"description\":\"For smaller shipments, documents, and packages that need to move quickly, our courier services offer secure and efficient delivery across the U.S. and globally. From next-day delivery to international express, we&#039;ve got you covered.\",\"key_features\":[\"Same-Day &amp; Next-Day Delivery\",\"Secure Packaging &amp; Handling\",\"Door-to-Door Delivery\",\"Tracking &amp; Notifications\"],\"cta_text\":\"Track Shipment\",\"cta_link\":\"/track.php\"},{\"image\":\"/asset/service8_1768432163_9339.jpg\",\"title\":\"Repackaging Services\",\"description\":\"We help you save space, reduce shipping costs, and comply with customs requirements through our professional repackaging solutions. Whether for retail, export, or warehousing, we ensure your goods are secure and presentation-ready.\",\"key_features\":[\"Custom Packaging Solutions\",\"Cost Optimization\",\"Labeling &amp; Compliance\",\"Product Repacking for Retail\"],\"cta_text\":\"Track Shipment\",\"cta_link\":\"/track.php\"}],\"download_section\":{\"heading\":\"FIND ALL IN ONE DOCUMENT\",\"text\":\"Download our service Brochures\",\"button_text\":\"Download PDF\",\"button_link\":\"#\"}}', '2026-01-14 19:07:59', '2026-01-14 23:09:23'),
(3, 'faq', 'FAQ', '{\"hero\": {\"heading\": \"Frequently Asked Questions\", \"subtitle\": \"Common questions about {company}\"}, \"items\": [{\"question\": \"What services does {company} offer?\", \"answer\": \"{company} offers a comprehensive range of shipping and logistics services including sea freight, air freight, haulage and local transport, bulk cargo handling, clearing and customs brokerage, import and duty finance, courier services, and repackaging services.\"}, {\"question\": \"How can I track my shipment?\", \"answer\": \"You can track your shipment by entering your tracking number on our tracking page. {company} provides real-time tracking updates for most of our shipping services.\"}, {\"question\": \"What payment methods does {company} accept?\", \"answer\": \"{company} accepts various payment methods. Please contact our customer service team for more information about payment options available in your region.\"}, {\"question\": \"Does {company} offer international shipping?\", \"answer\": \"Yes, {company} offers international shipping services including sea freight and air freight to destinations worldwide. Our team handles all necessary documentation and customs clearance.\"}, {\"question\": \"How can I contact {company} customer service?\", \"answer\": \"You can contact {company} customer service through our website contact form, email, or phone. Our support team is available to assist you with any questions or concerns about your shipments.\"}]}', '2026-01-14 19:08:00', '2026-01-14 19:08:00');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `setting_key`, `setting_value`, `updated_at`) VALUES
(1, 'google_maps_api_key', 'AIzaSyDcWKVHACCy6CVx8H5a7djYjixjMhozsjQ', '2026-01-17 20:11:28'),
(2, 'company_name', 'TopTier Logistics', '2026-01-14 22:57:31'),
(3, 'company_tagline', 'Global Logistics Solutions', '2026-01-14 22:57:31'),
(4, 'company_logo', '/asset/logo_uploaded_1768431461.png', '2026-01-14 22:57:41'),
(5, 'site_favicon', '/asset/favicon_uploaded_1768431461.png', '2026-01-14 22:57:41'),
(6, 'site_title', 'Shipping, Logistics Management and Supply Chain Management', '2026-01-14 19:07:59'),
(7, 'cookie_message', 'This website uses cookies and similar technologies (collectively cookies). We use functional, analytical and tracking cookies. For functional cookies we do not require your consent. However, we need your consent for all optional analytical and tracking cookies.', '2026-01-14 19:07:59'),
(10, 'primary_color', '#152E56', '2026-01-14 22:58:27'),
(11, 'secondary_color', '#F9BA34', '2026-01-14 22:57:31');

-- --------------------------------------------------------

--
-- Table structure for table `shipments`
--

CREATE TABLE `shipments` (
  `id` int(11) NOT NULL,
  `tracking_number` varchar(20) NOT NULL,
  `sender_name` varchar(255) NOT NULL,
  `sender_address` text NOT NULL,
  `sender_city` varchar(100) DEFAULT NULL,
  `sender_state` varchar(50) DEFAULT NULL,
  `sender_zip` varchar(20) DEFAULT NULL,
  `sender_country` varchar(50) DEFAULT 'United States',
  `sender_email` varchar(255) DEFAULT NULL,
  `sender_phone` varchar(50) DEFAULT NULL,
  `recipient_name` varchar(255) NOT NULL,
  `recipient_address` text NOT NULL,
  `recipient_city` varchar(100) DEFAULT NULL,
  `recipient_state` varchar(50) DEFAULT NULL,
  `recipient_zip` varchar(20) DEFAULT NULL,
  `recipient_country` varchar(50) DEFAULT 'United States',
  `recipient_email` varchar(255) DEFAULT NULL,
  `recipient_phone` varchar(50) DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL,
  `dimensions` varchar(50) DEFAULT NULL,
  `service_type` varchar(100) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Label Created',
  `estimated_delivery` date DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `sender_latitude` decimal(10,8) DEFAULT NULL,
  `sender_longitude` decimal(11,8) DEFAULT NULL,
  `recipient_latitude` decimal(10,8) DEFAULT NULL,
  `recipient_longitude` decimal(11,8) DEFAULT NULL,
  `pickup_location` varchar(255) DEFAULT NULL,
  `pickup_latitude` decimal(10,8) DEFAULT NULL,
  `pickup_longitude` decimal(11,8) DEFAULT NULL,
  `dropoff_location` varchar(255) DEFAULT NULL,
  `dropoff_latitude` decimal(10,8) DEFAULT NULL,
  `dropoff_longitude` decimal(11,8) DEFAULT NULL,
  `shipment_worth` decimal(10,2) DEFAULT NULL,
  `base_cost` decimal(10,2) DEFAULT NULL,
  `clearance_cost` decimal(10,2) DEFAULT NULL,
  `total_cost` decimal(10,2) DEFAULT NULL,
  `item_image` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `shipments`
--

INSERT INTO `shipments` (`id`, `tracking_number`, `sender_name`, `sender_address`, `sender_city`, `sender_state`, `sender_zip`, `sender_country`, `sender_email`, `sender_phone`, `recipient_name`, `recipient_address`, `recipient_city`, `recipient_state`, `recipient_zip`, `recipient_country`, `recipient_email`, `recipient_phone`, `weight`, `dimensions`, `service_type`, `status`, `estimated_delivery`, `reference_number`, `sender_latitude`, `sender_longitude`, `recipient_latitude`, `recipient_longitude`, `pickup_location`, `pickup_latitude`, `pickup_longitude`, `dropoff_location`, `dropoff_latitude`, `dropoff_longitude`, `shipment_worth`, `base_cost`, `total_cost`, `item_image`, `created_at`, `updated_at`) VALUES
(1, '8846 2251 1481', 'Professor', '23 bison street', 'Lake placid', 'New york', '28130', 'United States', 'mammamia@gmail.com', '+1(518)4598023', 'Utocha', '15th street', 'Birmingham', 'United Kingdom', '4100012', 'United States', 'otuocha@gmail.com', '+443698642882', 50.00, '12×10×9', 'TopTier Logistics 2Day', 'Exception', '2026-01-17', 'REF-20260116-44643', NULL, NULL, NULL, NULL, 'United States', NULL, NULL, 'Germany', NULL, NULL, 100000.00, 3150.00, 5150.00, '/asset/shipment_images/shipment_1768522523_2259.jpg', '2026-01-16 00:15:23', '2026-01-17 21:33:16');

-- --------------------------------------------------------

--
-- Table structure for table `tracking_events`
--

CREATE TABLE `tracking_events` (
  `id` int(11) NOT NULL,
  `shipment_id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `event_date` datetime NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tracking_events`
--

INSERT INTO `tracking_events` (`id`, `shipment_id`, `event_type`, `description`, `location`, `latitude`, `longitude`, `event_date`, `created_at`) VALUES
(1, 1, 'Label Created', 'Shipment label created', 'United States', NULL, NULL, '2026-01-16 00:15:23', '2026-01-16 00:15:23'),
(2, 1, 'Admin Note', 'Shipment has been created', 'United States', NULL, NULL, '2026-01-17 21:33:16', '2026-01-16 00:15:23'),
(3, 1, 'Exception', 'Exception — United States Postal Service', 'United States Postal Service', 40.71648690, -74.03715630, '2026-01-17 21:33:16', '2026-01-17 21:33:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `idx_username` (`username`);

--
-- Indexes for table `cookie_preferences`
--
ALTER TABLE `cookie_preferences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_session_id` (`session_id`);

--
-- Indexes for table `pages`
--
ALTER TABLE `pages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `page_slug` (`page_slug`),
  ADD KEY `idx_page_slug` (`page_slug`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- Indexes for table `shipments`
--
ALTER TABLE `shipments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tracking_number` (`tracking_number`),
  ADD KEY `idx_tracking_number` (`tracking_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `tracking_events`
--
ALTER TABLE `tracking_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shipment_id` (`shipment_id`),
  ADD KEY `idx_event_date` (`event_date`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `cookie_preferences`
--
ALTER TABLE `cookie_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pages`
--
ALTER TABLE `pages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `shipments`
--
ALTER TABLE `shipments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `tracking_events`
--
ALTER TABLE `tracking_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `tracking_events`
--
ALTER TABLE `tracking_events`
  ADD CONSTRAINT `tracking_events_ibfk_1` FOREIGN KEY (`shipment_id`) REFERENCES `shipments` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
