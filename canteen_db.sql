-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: canteen_db
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `admins`
--

DROP TABLE IF EXISTS `admins`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `admins`
--

LOCK TABLES `admins` WRITE;
/*!40000 ALTER TABLE `admins` DISABLE KEYS */;
INSERT INTO `admins` VALUES (1,'admin','12345','2026-08-30 02:22:37');
/*!40000 ALTER TABLE `admins` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(150) NOT NULL,
  `subject` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacts`
--

LOCK TABLES `contacts` WRITE;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
INSERT INTO `contacts` VALUES (1,'Mohan Raj','student_1788185258@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-08-31 14:07:38'),(2,'Mohan Raj','student_1788185291@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-08-31 14:08:12'),(3,'Mohan Raj','student_1788186193@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-08-31 14:23:14'),(4,'Mohan Raj','student_1788187013@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-08-31 14:36:54'),(5,'Mohan Raj','student_1788232558@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-09-01 03:15:59'),(6,'Mohan Raj','student_1788232832@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-09-01 03:20:33'),(7,'Mohan Raj','student_1788263441@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-09-01 11:50:42'),(8,'Mohan Raj','student_1788263487@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-09-01 11:51:28'),(9,'Mohan Raj','student_1788263590@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-09-01 11:53:10'),(10,'Mohan Raj','student_1788263655@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-09-01 11:54:16'),(11,'Mohan Raj','student_1788264047@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-09-01 12:00:48'),(12,'Mohan Raj','student_1788264299@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-09-01 12:05:00'),(13,'Mohan Raj','student_1788264337@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-09-01 12:05:37'),(14,'Mohan Raj','student_1788264699@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-09-01 12:11:40'),(15,'Mohan Raj','student_1788271828@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-09-01 14:10:29'),(16,'Mohan Raj','student_1788271866@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-09-01 14:11:07'),(17,'Mohan Raj','student_1788272010@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-09-01 14:13:31'),(18,'Mohan Raj','student_1788272239@college.edu','Food Quality Rating','The food quality and ordering experience is super smooth!','2026-09-01 14:17:19');
/*!40000 ALTER TABLE `contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `payment_id` varchar(255) DEFAULT NULL,
  `bank_utr` varchar(100) DEFAULT NULL,
  `merchant_order_id` varchar(255) DEFAULT NULL,
  `payment_method` varchar(100) DEFAULT 'UroPay',
  `qr_code` mediumtext DEFAULT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Pending',
  `food_status` varchar(50) NOT NULL DEFAULT 'Preparing',
  `order_date` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `orders`
--

LOCK TABLES `orders` WRITE;
/*!40000 ALTER TABLE `orders` DISABLE KEYS */;
INSERT INTO `orders` VALUES (1,1,136.50,'URPYGOLF986257',NULL,'CANTEEN202608300519442842','UroPay',NULL,'Cancelled','Preparing','2026-08-30 03:19:45'),(2,1,21.00,'URPYOSCAR464498',NULL,'CANTEEN202608300544233200','UroPay',NULL,'Cancelled','Preparing','2026-08-30 03:44:23'),(3,1,15.75,'URPYUNIFORM578286',NULL,'CANTEEN202608300602565358','UroPay',NULL,'Completed','Delivered','2026-08-30 04:02:57'),(4,1,21.00,'URPYFOXTROT365027',NULL,'CANTEEN202608300649238047','UroPay',NULL,'Cancelled','Preparing','2026-08-30 04:49:24'),(5,1,21.00,'URPYKILO379733',NULL,'CANTEEN202608300649381293','UroPay',NULL,'Cancelled','Preparing','2026-08-30 04:49:38'),(6,1,15.75,'COB1F1A4364250165D09BA4F37124F33A48',NULL,'CANTEEN202608300746265582','UroPay',NULL,'Completed','Delivered','2026-08-30 05:46:27'),(7,1,26.25,'URPYBRAVO648213',NULL,'CANTEEN202608300817269482','UroPay',NULL,'Cancelled','Preparing','2026-08-30 06:17:27'),(8,1,21.00,'URPYALPHA438012',NULL,'CANTEEN202608300847164294','UroPay',NULL,'Cancelled','Preparing','2026-08-30 06:47:17'),(9,1,15.75,'URPYVICTOR098073',NULL,'CANTEEN202608300858163212','UroPay',NULL,'Cancelled','Preparing','2026-08-30 06:58:16'),(10,1,21.00,'URPYBRAVO271885',NULL,'CANTEEN202608300901103654','UroPay',NULL,'Cancelled','Preparing','2026-08-30 07:01:10'),(11,1,15.75,'URPYJULIET292069',NULL,'CANTEEN202608300901302138','UroPay',NULL,'Cancelled','Preparing','2026-08-30 07:01:30'),(12,1,15.75,'URPYVICTOR453020',NULL,'CANTEEN202608311610504639','UroPay',NULL,'Cancelled','Preparing','2026-08-31 14:10:51'),(13,1,10.50,'URPYQUEBEC812228',NULL,'CANTEEN202608311616507024','UroPay',NULL,'Completed','Preparing','2026-08-31 14:16:51'),(14,1,10.50,'URPYKILO252051','661060705932','CANTEEN202608311624105409','UroPay',NULL,'Completed','Preparing','2026-08-31 14:24:10'),(15,1,10.50,'URPYLIMA099737','661099887766','CANTEEN202609010541392026','UroPay','data:image/png;base64,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','Completed','Preparing','2026-09-01 03:41:40'),(16,1,10.50,'URPYROMEO779450',NULL,'CANTEEN202609011356183790','UroPay','data:image/png;base64,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','Completed','Preparing','2026-09-01 11:56:19'),(17,1,21.00,'URPYROMEO394562',NULL,'CANTEEN202609011406337909','UroPay','data:image/png;base64,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','Completed','Preparing','2026-09-01 12:06:34');
/*!40000 ALTER TABLE `orders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(150) NOT NULL,
  `otp` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_name` varchar(200) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image` varchar(255) NOT NULL,
  `status` varchar(50) NOT NULL DEFAULT 'Available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=37 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
INSERT INTO `products` VALUES (1,'Chicken Biryani','Spicy chicken biryani with rice and fresh herbs.',120.00,'Chicken Biryani.jpg','Available','2026-08-30 03:03:31'),(2,'Sandwich','Fresh veggie sandwich with crispy toast.',110.00,'Sandwich.jpg','Available','2026-08-30 03:03:31'),(3,'Noodles','Hot noodles with vegetables and sauces.',130.00,'Noodles.jpg','Available','2026-08-30 03:03:31'),(4,'Tea','Hot refreshing tea.',10.00,'Tea.jpg','Available','2026-08-30 03:22:13'),(5,'Samosa','Crispy samosa with filling.',25.00,'Samosa.jpg','Available','2026-08-30 03:22:13'),(6,'Cookies','Butter cookies and snacks.',20.00,'Cookies.jpg','Available','2026-08-30 03:22:13'),(7,'Fresh Juice','Orange or mixed fruit juice.',50.00,'Fresh Juice.jpg','Available','2026-08-30 03:22:13'),(8,'French Fries','Crispy golden fries.',60.00,'French Fries.jpg','Available','2026-08-30 03:22:13'),(9,'Idli','Soft idli with sambar and chutney.',40.00,'Idli.jpg','Available','2026-08-30 03:22:13'),(10,'Vada','Crispy vada snack.',15.00,'Vada.jpg','Available','2026-08-30 03:22:13'),(11,'Cake Slice','Fresh baked cake slice.',25.00,'Cake Slice.jpg','Available','2026-08-30 03:22:13'),(12,'Burger','Juicy grilled burger with fresh lettuce and tomato.',120.00,'Burger.jpg','Available','2026-08-31 13:55:56'),(13,'Pizza','Cheesy pizza with tomato sauce and herb toppings.',180.00,'pizza.jpg','Available','2026-08-31 13:55:56'),(14,'Dosa','Crispy masala dosa served with chutney and sambar.',90.00,'dosa.jpg','Available','2026-08-31 13:55:56'),(15,'Coffee','Fresh hot coffee for a quick energy boost.',60.00,'coffee.jpg','Available','2026-08-31 13:55:56'),(16,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-08-31 14:08:12'),(17,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-08-31 14:23:14'),(18,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-08-31 14:36:54'),(19,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-09-01 03:15:59'),(20,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-09-01 03:20:33'),(21,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-09-01 11:50:42'),(22,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-09-01 11:51:28'),(23,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-09-01 11:53:10'),(24,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-09-01 11:54:16'),(25,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-09-01 12:00:48'),(26,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-09-01 12:05:00'),(27,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-09-01 12:05:38'),(28,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-09-01 12:11:40'),(29,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-09-01 14:10:30'),(30,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-09-01 14:11:07'),(31,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-09-01 14:13:31'),(32,'Chef Special Noodles','Delicious spicy noodles with veggies',110.00,'','Available','2026-09-01 14:17:19'),(33,'Burger','Juicy grilled burger with fresh lettuce and tomato.',120.00,'Burger.jpg','Available','2026-09-01 14:44:48'),(34,'Pizza','Cheesy pizza with tomato sauce and herb toppings.',180.00,'pizza.jpg','Available','2026-09-01 14:44:48'),(35,'Dosa','Crispy masala dosa served with chutney and sambar.',90.00,'dosa.jpg','Available','2026-09-01 14:44:48'),(36,'Coffee','Fresh hot coffee for a quick energy boost.',60.00,'coffee.jpg','Available','2026-09-01 14:44:48');
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `regno` varchar(100) NOT NULL,
  `department` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `regno` (`regno`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'mohanraj','13562','BCA','mohanraj.s4211@gmail.com','$2y$10$DcEyQ.ecFgLveRijmYz4tuCBN3qYODflR7t2GVb4wMXVOObqqnMlK','2026-08-30 02:37:15'),(2,'Student Demo','STU001','BCA','studentdemo@gmail.com',' Fatal error: Uncaught Error: Undefined constant student123 in Command line code:1 Stack trace: #0 {main} thrown in Command line code on line 1','2026-08-30 03:02:00'),(3,'Mohan Raj','REG1788185258','BCA','student_1788185258@college.edu','$2y$10$dGQQ/A0ZgvlEPPboHJK.yuqHXh5m24Pt5g7uQhH7UqoUwwv5iE.FO','2026-08-31 14:07:38'),(4,'Mohan Raj','REG1788185291','BCA','student_1788185291@college.edu','$2y$10$gwqMxYShf5HKuKdkxlZkYe9syLsFidlOMT6g8Ku1hNxzDO3MolXGi','2026-08-31 14:08:11'),(5,'Mohan Raj','REG1788186193','BCA','student_1788186193@college.edu','$2y$10$4MghJMaihO.pz/v6K667W.L5D4btyJdhBeuW84FIt.zk3pD2oyX5C','2026-08-31 14:23:14'),(6,'Mohan Raj','REG1788187013','BCA','student_1788187013@college.edu','$2y$10$lLjgcgJsTQ5yOSk8ifSsdeHDnTyBn2TW1.4zDK8w85dLzqL67/YIO','2026-08-31 14:36:53'),(7,'Mohan Raj','REG1788232558','BCA','student_1788232558@college.edu','$2y$10$lC5tXJe/JGV2M8nZ7TQPGuehfY3qdW51IVyVqui3QyGtPJmKwn9sO','2026-09-01 03:15:58'),(8,'Mohan Raj','REG1788232832','BCA','student_1788232832@college.edu','$2y$10$FfPRIgGiyz.ddZWTgovvLuBUABEoe47khXQY8WtNMc25drG4phL76','2026-09-01 03:20:32'),(9,'Mohan Test','REG26629','CSE','testuser_1788234118@college.edu','$2y$10$ABcHxlHtHo0ByVioUWIr5uafl48iN8kXGcsYxWrGFr0bl1dzOlYvS','2026-09-01 03:41:58'),(10,'Mohan Test','REG60684','CSE','testuser_1788263434@college.edu','$2y$10$vP1VMn/dfjt2.b1eYcv44Oso/k08YfAZDHvGLyQNSAV3r/MiGzDwC','2026-09-01 11:50:34'),(11,'Mohan Raj','REG1788263441','BCA','student_1788263441@college.edu','$2y$10$AnMGANM0iDyAIPlxOY8YHOrT9eoMUvdv4nh3t4E2GBjL/XMaYb8Y2','2026-09-01 11:50:41'),(12,'Mohan Raj','REG1788263487','BCA','student_1788263487@college.edu','$2y$10$J46XdYgqHeX/mgTcVO2caeQZ8n8KHlFqhEdgxJr6Jz8.6V12rGlcy','2026-09-01 11:51:28'),(13,'Mohan Test','REG98088','CSE','testuser_1788263583@college.edu','$2y$10$xk0/SbE9RrTfFHYavflRc.CrCFeAaIad9qd/MbQ97igJ1mDv4yh5G','2026-09-01 11:53:03'),(14,'Mohan Raj','REG1788263590','BCA','student_1788263590@college.edu','$2y$10$mNQXJCH6L9kAZ3pAdvNZ8uAoFcDMM2mypkauKq2UcxR7b//V7g7lW','2026-09-01 11:53:10'),(15,'Mohan Raj','REG1788263655','BCA','student_1788263655@college.edu','$2y$10$i5DGzg6ZmX.D4uCBsConheEMGlk5Cu3AqiEKHSOvGLY.2DwJ7QdxW','2026-09-01 11:54:16'),(16,'Mohan Raj','REG1788264047','BCA','student_1788264047@college.edu','$2y$10$TNKiOMdNp5R.6jpy7Xhb2uiZ5pDhm/xL901bJzEfg/vWgR5pCdtm.','2026-09-01 12:00:47'),(17,'Mohan Raj','REG1788264299','BCA','student_1788264299@college.edu','$2y$10$2ofv1yaaMAbQoZxsi7lxzOwaowU4yGZxJELmBlE7lBH5dcKCqUnPC','2026-09-01 12:04:59'),(18,'Mohan Raj','REG1788264337','BCA','student_1788264337@college.edu','$2y$10$TnnEHqdM47oVVYfqjv8KceMLSBHIU2aIuzjcr937Th8PT4RTlqhu.','2026-09-01 12:05:37'),(19,'Mohan Raj','REG1788264699','BCA','student_1788264699@college.edu','$2y$10$bcmL9a/18g7UW5XCt0OJuux4rrPKCaELkeWZAUnZlYAxNemUvQ/y.','2026-09-01 12:11:39'),(20,'Mohan Raj','REG1788271828','BCA','student_1788271828@college.edu','$2y$10$.qT/YlGp/XgP0m/pJ86S7e5hNgXBJk6gn6Q8lEa.taQI56mGF01UK','2026-09-01 14:10:29'),(21,'Mohan Raj','REG1788271866','BCA','student_1788271866@college.edu','$2y$10$9yBqvbN2NOyvteQyWz6qv.gl3Vvpn1R8qcbGL1zmH2MLADBqEmtFS','2026-09-01 14:11:06'),(22,'Mohan Raj','REG1788272010','BCA','student_1788272010@college.edu','$2y$10$iS1f.d4sfjT5ix5IjhSl/uNT.t0QjFjPPBevVMzxFnp3FJ2GhkzOa','2026-09-01 14:13:30'),(23,'Mohan Raj','REG1788272239','BCA','student_1788272239@college.edu','$2y$10$Lh/rPDIuvHwsiTiBa5c51OLNNzxxtqo8eGJhs6QeXPdLwEDKqeBKy','2026-09-01 14:17:19');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-09-01 20:16:07
