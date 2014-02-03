-- MySQL dump 10.13  Distrib 5.5.35, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: ninja
-- ------------------------------------------------------
-- Server version	5.5.35-0ubuntu0.13.10.2

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `account_gateways`
--

DROP TABLE IF EXISTS `account_gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_gateways` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `gateway_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `config` text COLLATE utf8_unicode_ci NOT NULL,
  `public_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_gateways_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `account_gateways_gateway_id_foreign` (`gateway_id`),
  KEY `account_gateways_user_id_foreign` (`user_id`),
  KEY `account_gateways_public_id_index` (`public_id`),
  CONSTRAINT `account_gateways_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_gateways_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_gateways_gateway_id_foreign` FOREIGN KEY (`gateway_id`) REFERENCES `gateways` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `account_gateways`
--

LOCK TABLES `account_gateways` WRITE;
/*!40000 ALTER TABLE `account_gateways` DISABLE KEYS */;
/*!40000 ALTER TABLE `account_gateways` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `accounts`
--

DROP TABLE IF EXISTS `accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `timezone_id` int(10) unsigned DEFAULT NULL,
  `date_format_id` int(10) unsigned DEFAULT NULL,
  `datetime_format_id` int(10) unsigned DEFAULT NULL,
  `currency_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `ip` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `account_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `last_login` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `address1` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `address2` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `city` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `state` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `postal_code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `country_id` int(10) unsigned DEFAULT NULL,
  `invoice_terms` text COLLATE utf8_unicode_ci NOT NULL,
  `email_footer` text COLLATE utf8_unicode_ci NOT NULL,
  `industry_id` int(10) unsigned DEFAULT NULL,
  `size_id` int(10) unsigned DEFAULT NULL,
  `invoice_taxes` tinyint(1) NOT NULL DEFAULT '1',
  `invoice_item_taxes` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `accounts_account_key_unique` (`account_key`),
  KEY `accounts_timezone_id_foreign` (`timezone_id`),
  KEY `accounts_date_format_id_foreign` (`date_format_id`),
  KEY `accounts_datetime_format_id_foreign` (`datetime_format_id`),
  KEY `accounts_country_id_foreign` (`country_id`),
  KEY `accounts_currency_id_foreign` (`currency_id`),
  KEY `accounts_industry_id_foreign` (`industry_id`),
  KEY `accounts_size_id_foreign` (`size_id`),
  CONSTRAINT `accounts_size_id_foreign` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`id`),
  CONSTRAINT `accounts_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `accounts_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `accounts_datetime_format_id_foreign` FOREIGN KEY (`datetime_format_id`) REFERENCES `datetime_formats` (`id`),
  CONSTRAINT `accounts_date_format_id_foreign` FOREIGN KEY (`date_format_id`) REFERENCES `date_formats` (`id`),
  CONSTRAINT `accounts_industry_id_foreign` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`),
  CONSTRAINT `accounts_timezone_id_foreign` FOREIGN KEY (`timezone_id`) REFERENCES `timezones` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `accounts`
--

LOCK TABLES `accounts` WRITE;
/*!40000 ALTER TABLE `accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `activities`
--

DROP TABLE IF EXISTS `activities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `activities` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `account_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `contact_id` int(10) unsigned NOT NULL,
  `payment_id` int(10) unsigned NOT NULL,
  `invoice_id` int(10) unsigned NOT NULL,
  `credit_id` int(10) unsigned NOT NULL,
  `invitation_id` int(10) unsigned NOT NULL,
  `message` text COLLATE utf8_unicode_ci NOT NULL,
  `json_backup` text COLLATE utf8_unicode_ci NOT NULL,
  `activity_type_id` int(11) NOT NULL,
  `adjustment` decimal(13,4) NOT NULL,
  `balance` decimal(13,4) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `activities_account_id_foreign` (`account_id`),
  KEY `activities_client_id_foreign` (`client_id`),
  CONSTRAINT `activities_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `activities_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `activities`
--

LOCK TABLES `activities` WRITE;
/*!40000 ALTER TABLE `activities` DISABLE KEYS */;
/*!40000 ALTER TABLE `activities` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clients`
--

DROP TABLE IF EXISTS `clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `currency_id` int(10) unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `address1` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `address2` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `city` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `state` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `postal_code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `country_id` int(10) unsigned DEFAULT NULL,
  `work_phone` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `private_notes` text COLLATE utf8_unicode_ci NOT NULL,
  `balance` decimal(13,4) NOT NULL,
  `paid_to_date` decimal(13,4) NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `website` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `industry_id` int(10) unsigned DEFAULT NULL,
  `size_id` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL,
  `payment_terms` int(11) NOT NULL,
  `public_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clients_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `clients_user_id_foreign` (`user_id`),
  KEY `clients_country_id_foreign` (`country_id`),
  KEY `clients_industry_id_foreign` (`industry_id`),
  KEY `clients_size_id_foreign` (`size_id`),
  KEY `clients_currency_id_foreign` (`currency_id`),
  KEY `clients_account_id_index` (`account_id`),
  KEY `clients_public_id_index` (`public_id`),
  CONSTRAINT `clients_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `clients_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clients_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `clients_industry_id_foreign` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`),
  CONSTRAINT `clients_size_id_foreign` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`id`),
  CONSTRAINT `clients_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clients`
--

LOCK TABLES `clients` WRITE;
/*!40000 ALTER TABLE `clients` DISABLE KEYS */;
/*!40000 ALTER TABLE `clients` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contacts`
--

DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL,
  `send_invoice` tinyint(1) NOT NULL,
  `first_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `last_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `last_login` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `public_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contacts_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `contacts_user_id_foreign` (`user_id`),
  KEY `contacts_client_id_index` (`client_id`),
  CONSTRAINT `contacts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contacts_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacts`
--

LOCK TABLES `contacts` WRITE;
/*!40000 ALTER TABLE `contacts` DISABLE KEYS */;
/*!40000 ALTER TABLE `contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `countries`
--

DROP TABLE IF EXISTS `countries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `countries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `capital` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `citizenship` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `country_code` varchar(3) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `currency` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `currency_code` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `currency_sub_unit` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `full_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `iso_3166_2` varchar(2) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `iso_3166_3` varchar(3) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `region_code` varchar(3) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `sub_region_code` varchar(3) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  `eea` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=895 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `countries`
--

LOCK TABLES `countries` WRITE;
/*!40000 ALTER TABLE `countries` DISABLE KEYS */;
INSERT INTO `countries` VALUES (4,'Kabul','Afghan','004','afghani','AFN','pul','Islamic Republic of Afghanistan','AF','AFG','Afghanistan','142','034',0),(8,'Tirana','Albanian','008','lek','ALL','(qindar (pl. qindarka))','Republic of Albania','AL','ALB','Albania','150','039',0),(10,'Antartica','of Antartica','010','','','','Antarctica','AQ','ATA','Antarctica','','',0),(12,'Algiers','Algerian','012','Algerian dinar','DZD','centime','People’s Democratic Republic of Algeria','DZ','DZA','Algeria','002','015',0),(16,'Pago Pago','American Samoan','016','US dollar','USD','cent','Territory of American','AS','ASM','American Samoa','009','061',0),(20,'Andorra la Vella','Andorran','020','euro','EUR','cent','Principality of Andorra','AD','AND','Andorra','150','039',0),(24,'Luanda','Angolan','024','kwanza','AOA','cêntimo','Republic of Angola','AO','AGO','Angola','002','017',0),(28,'St John’s','of Antigua and Barbuda','028','East Caribbean dollar','XCD','cent','Antigua and Barbuda','AG','ATG','Antigua and Barbuda','019','029',0),(31,'Baku','Azerbaijani','031','Azerbaijani manat','AZN','kepik (inv.)','Republic of Azerbaijan','AZ','AZE','Azerbaijan','142','145',0),(32,'Buenos Aires','Argentinian','032','Argentine peso','ARS','centavo','Argentine Republic','AR','ARG','Argentina','019','005',0),(36,'Canberra','Australian','036','Australian dollar','AUD','cent','Commonwealth of Australia','AU','AUS','Australia','009','053',0),(40,'Vienna','Austrian','040','euro','EUR','cent','Republic of Austria','AT','AUT','Austria','150','155',1),(44,'Nassau','Bahamian','044','Bahamian dollar','BSD','cent','Commonwealth of the Bahamas','BS','BHS','Bahamas','019','029',0),(48,'Manama','Bahraini','048','Bahraini dinar','BHD','fils (inv.)','Kingdom of Bahrain','BH','BHR','Bahrain','142','145',0),(50,'Dhaka','Bangladeshi','050','taka (inv.)','BDT','poisha (inv.)','People’s Republic of Bangladesh','BD','BGD','Bangladesh','142','034',0),(51,'Yerevan','Armenian','051','dram (inv.)','AMD','luma','Republic of Armenia','AM','ARM','Armenia','142','145',0),(52,'Bridgetown','Barbadian','052','Barbados dollar','BBD','cent','Barbados','BB','BRB','Barbados','019','029',0),(56,'Brussels','Belgian','056','euro','EUR','cent','Kingdom of Belgium','BE','BEL','Belgium','150','155',1),(60,'Hamilton','Bermudian','060','Bermuda dollar','BMD','cent','Bermuda','BM','BMU','Bermuda','019','021',0),(64,'Thimphu','Bhutanese','064','ngultrum (inv.)','BTN','chhetrum (inv.)','Kingdom of Bhutan','BT','BTN','Bhutan','142','034',0),(68,'Sucre (BO1)','Bolivian','068','boliviano','BOB','centavo','Plurinational State of Bolivia','BO','BOL','Bolivia, Plurinational State of','019','005',0),(70,'Sarajevo','of Bosnia and Herzegovina','070','convertible mark','BAM','fening','Bosnia and Herzegovina','BA','BIH','Bosnia and Herzegovina','150','039',0),(72,'Gaborone','Botswanan','072','pula (inv.)','BWP','thebe (inv.)','Republic of Botswana','BW','BWA','Botswana','002','018',0),(74,'Bouvet island','of Bouvet island','074','','','','Bouvet Island','BV','BVT','Bouvet Island','','',0),(76,'Brasilia','Brazilian','076','real (pl. reais)','BRL','centavo','Federative Republic of Brazil','BR','BRA','Brazil','019','005',0),(84,'Belmopan','Belizean','084','Belize dollar','BZD','cent','Belize','BZ','BLZ','Belize','019','013',0),(86,'Diego Garcia','Changosian','086','US dollar','USD','cent','British Indian Ocean Territory','IO','IOT','British Indian Ocean Territory','','',0),(90,'Honiara','Solomon Islander','090','Solomon Islands dollar','SBD','cent','Solomon Islands','SB','SLB','Solomon Islands','009','054',0),(92,'Road Town','British Virgin Islander;','092','US dollar','USD','cent','British Virgin Islands','VG','VGB','Virgin Islands, British','019','029',0),(96,'Bandar Seri Begawan','Bruneian','096','Brunei dollar','BND','sen (inv.)','Brunei Darussalam','BN','BRN','Brunei Darussalam','142','035',0),(100,'Sofia','Bulgarian','100','lev (pl. leva)','BGN','stotinka','Republic of Bulgaria','BG','BGR','Bulgaria','150','151',1),(104,'Yangon','Burmese','104','kyat','MMK','pya','Union of Myanmar/','MM','MMR','Myanmar','142','035',0),(108,'Bujumbura','Burundian','108','Burundi franc','BIF','centime','Republic of Burundi','BI','BDI','Burundi','002','014',0),(112,'Minsk','Belarusian','112','Belarusian rouble','BYR','kopek','Republic of Belarus','BY','BLR','Belarus','150','151',0),(116,'Phnom Penh','Cambodian','116','riel','KHR','sen (inv.)','Kingdom of Cambodia','KH','KHM','Cambodia','142','035',0),(120,'Yaoundé','Cameroonian','120','CFA franc (BEAC)','XAF','centime','Republic of Cameroon','CM','CMR','Cameroon','002','017',0),(124,'Ottawa','Canadian','124','Canadian dollar','CAD','cent','Canada','CA','CAN','Canada','019','021',0),(132,'Praia','Cape Verdean','132','Cape Verde escudo','CVE','centavo','Republic of Cape Verde','CV','CPV','Cape Verde','002','011',0),(136,'George Town','Caymanian','136','Cayman Islands dollar','KYD','cent','Cayman Islands','KY','CYM','Cayman Islands','019','029',0),(140,'Bangui','Central African','140','CFA franc (BEAC)','XAF','centime','Central African Republic','CF','CAF','Central African Republic','002','017',0),(144,'Colombo','Sri Lankan','144','Sri Lankan rupee','LKR','cent','Democratic Socialist Republic of Sri Lanka','LK','LKA','Sri Lanka','142','034',0),(148,'N’Djamena','Chadian','148','CFA franc (BEAC)','XAF','centime','Republic of Chad','TD','TCD','Chad','002','017',0),(152,'Santiago','Chilean','152','Chilean peso','CLP','centavo','Republic of Chile','CL','CHL','Chile','019','005',0),(156,'Beijing','Chinese','156','renminbi-yuan (inv.)','CNY','jiao (10)','People’s Republic of China','CN','CHN','China','142','030',0),(158,'Taipei','Taiwanese','158','new Taiwan dollar','TWD','fen (inv.)','Republic of China, Taiwan (TW1)','TW','TWN','Taiwan, Province of China','142','030',0),(162,'Flying Fish Cove','Christmas Islander','162','Australian dollar','AUD','cent','Christmas Island Territory','CX','CXR','Christmas Island','','',0),(166,'Bantam','Cocos Islander','166','Australian dollar','AUD','cent','Territory of Cocos (Keeling) Islands','CC','CCK','Cocos (Keeling) Islands','','',0),(170,'Santa Fe de Bogotá','Colombian','170','Colombian peso','COP','centavo','Republic of Colombia','CO','COL','Colombia','019','005',0),(174,'Moroni','Comorian','174','Comorian franc','KMF','','Union of the Comoros','KM','COM','Comoros','002','014',0),(175,'Mamoudzou','Mahorais','175','euro','EUR','cent','Departmental Collectivity of Mayotte','YT','MYT','Mayotte','002','014',0),(178,'Brazzaville','Congolese','178','CFA franc (BEAC)','XAF','centime','Republic of the Congo','CG','COG','Congo','002','017',0),(180,'Kinshasa','Congolese','180','Congolese franc','CDF','centime','Democratic Republic of the Congo','CD','COD','Congo, the Democratic Republic of the','002','017',0),(184,'Avarua','Cook Islander','184','New Zealand dollar','NZD','cent','Cook Islands','CK','COK','Cook Islands','009','061',0),(188,'San José','Costa Rican','188','Costa Rican colón (pl. colones)','CRC','céntimo','Republic of Costa Rica','CR','CRI','Costa Rica','019','013',0),(191,'Zagreb','Croat','191','kuna (inv.)','HRK','lipa (inv.)','Republic of Croatia','HR','HRV','Croatia','150','039',1),(192,'Havana','Cuban','192','Cuban peso','CUP','centavo','Republic of Cuba','CU','CUB','Cuba','019','029',0),(196,'Nicosia','Cypriot','196','euro','EUR','cent','Republic of Cyprus','CY','CYP','Cyprus','142','145',1),(203,'Prague','Czech','203','Czech koruna (pl. koruny)','CZK','halér','Czech Republic','CZ','CZE','Czech Republic','150','151',1),(204,'Porto Novo (BJ1)','Beninese','204','CFA franc (BCEAO)','XOF','centime','Republic of Benin','BJ','BEN','Benin','002','011',0),(208,'Copenhagen','Dane','208','Danish krone','DKK','øre (inv.)','Kingdom of Denmark','DK','DNK','Denmark','150','154',1),(212,'Roseau','Dominican','212','East Caribbean dollar','XCD','cent','Commonwealth of Dominica','DM','DMA','Dominica','019','029',0),(214,'Santo Domingo','Dominican','214','Dominican peso','DOP','centavo','Dominican Republic','DO','DOM','Dominican Republic','019','029',0),(218,'Quito','Ecuadorian','218','US dollar','USD','cent','Republic of Ecuador','EC','ECU','Ecuador','019','005',0),(222,'San Salvador','Salvadorian; Salvadoran','222','Salvadorian colón (pl. colones)','SVC','centavo','Republic of El Salvador','SV','SLV','El Salvador','019','013',0),(226,'Malabo','Equatorial Guinean','226','CFA franc (BEAC)','XAF','centime','Republic of Equatorial Guinea','GQ','GNQ','Equatorial Guinea','002','017',0),(231,'Addis Ababa','Ethiopian','231','birr (inv.)','ETB','cent','Federal Democratic Republic of Ethiopia','ET','ETH','Ethiopia','002','014',0),(232,'Asmara','Eritrean','232','nakfa','ERN','cent','State of Eritrea','ER','ERI','Eritrea','002','014',0),(233,'Tallinn','Estonian','233','euro','EUR','cent','Republic of Estonia','EE','EST','Estonia','150','154',1),(234,'Tórshavn','Faeroese','234','Danish krone','DKK','øre (inv.)','Faeroe Islands','FO','FRO','Faroe Islands','150','154',0),(238,'Stanley','Falkland Islander','238','Falkland Islands pound','FKP','new penny','Falkland Islands','FK','FLK','Falkland Islands (Malvinas)','019','005',0),(239,'King Edward Point (Grytviken)','of South Georgia and the South Sandwich Islands','239','','','','South Georgia and the South Sandwich Islands','GS','SGS','South Georgia and the South Sandwich Islands','','',0),(242,'Suva','Fijian','242','Fiji dollar','FJD','cent','Republic of Fiji','FJ','FJI','Fiji','009','054',0),(246,'Helsinki','Finn','246','euro','EUR','cent','Republic of Finland','FI','FIN','Finland','150','154',1),(248,'Mariehamn','Åland Islander','248','euro','EUR','cent','Åland Islands','AX','ALA','Åland Islands','150','154',0),(250,'Paris','Frenchman; Frenchwoman','250','euro','EUR','cent','French Republic','FR','FRA','France','150','155',1),(254,'Cayenne','Guianese','254','euro','EUR','cent','French Guiana','GF','GUF','French Guiana','019','005',0),(258,'Papeete','Polynesian','258','CFP franc','XPF','centime','French Polynesia','PF','PYF','French Polynesia','009','061',0),(260,'Port-aux-Francais','of French Southern and Antarctic Lands','260','euro','EUR','cent','French Southern and Antarctic Lands','TF','ATF','French Southern Territories','','',0),(262,'Djibouti','Djiboutian','262','Djibouti franc','DJF','','Republic of Djibouti','DJ','DJI','Djibouti','002','014',0),(266,'Libreville','Gabonese','266','CFA franc (BEAC)','XAF','centime','Gabonese Republic','GA','GAB','Gabon','002','017',0),(268,'Tbilisi','Georgian','268','lari','GEL','tetri (inv.)','Georgia','GE','GEO','Georgia','142','145',0),(270,'Banjul','Gambian','270','dalasi (inv.)','GMD','butut','Republic of the Gambia','GM','GMB','Gambia','002','011',0),(275,NULL,'Palisitnean','275',NULL,NULL,NULL,NULL,'PS','PSE','Palestinian Territory','142','145',0),(276,'Berlin','German','276','euro','EUR','cent','Federal Republic of Germany','DE','DEU','Germany','150','155',1),(288,'Accra','Ghanaian','288','Ghana cedi','GHS','pesewa','Republic of Ghana','GH','GHA','Ghana','002','011',0),(292,'Gibraltar','Gibraltarian','292','Gibraltar pound','GIP','penny','Gibraltar','GI','GIB','Gibraltar','150','039',0),(296,'Tarawa','Kiribatian','296','Australian dollar','AUD','cent','Republic of Kiribati','KI','KIR','Kiribati','009','057',0),(300,'Athens','Greek','300','euro','EUR','cent','Hellenic Republic','GR','GRC','Greece','150','039',1),(304,'Nuuk','Greenlander','304','Danish krone','DKK','øre (inv.)','Greenland','GL','GRL','Greenland','019','021',0),(308,'St George’s','Grenadian','308','East Caribbean dollar','XCD','cent','Grenada','GD','GRD','Grenada','019','029',0),(312,'Basse Terre','Guadeloupean','312','euro','EUR ','cent','Guadeloupe','GP','GLP','Guadeloupe','019','029',0),(316,'Agaña (Hagåtña)','Guamanian','316','US dollar','USD','cent','Territory of Guam','GU','GUM','Guam','009','057',0),(320,'Guatemala City','Guatemalan','320','quetzal (pl. quetzales)','GTQ','centavo','Republic of Guatemala','GT','GTM','Guatemala','019','013',0),(324,'Conakry','Guinean','324','Guinean franc','GNF','','Republic of Guinea','GN','GIN','Guinea','002','011',0),(328,'Georgetown','Guyanese','328','Guyana dollar','GYD','cent','Cooperative Republic of Guyana','GY','GUY','Guyana','019','005',0),(332,'Port-au-Prince','Haitian','332','gourde','HTG','centime','Republic of Haiti','HT','HTI','Haiti','019','029',0),(334,'Territory of Heard Island and McDonald Islands','of Territory of Heard Island and McDonald Islands','334','','','','Territory of Heard Island and McDonald Islands','HM','HMD','Heard Island and McDonald Islands','','',0),(336,'Vatican City','of the Holy See/of the Vatican','336','euro','EUR','cent','the Holy See/ Vatican City State','VA','VAT','Holy See (Vatican City State)','150','039',0),(340,'Tegucigalpa','Honduran','340','lempira','HNL','centavo','Republic of Honduras','HN','HND','Honduras','019','013',0),(344,'(HK3)','Hong Kong Chinese','344','Hong Kong dollar','HKD','cent','Hong Kong Special Administrative Region of the People’s Republic of China (HK2)','HK','HKG','Hong Kong','142','030',0),(348,'Budapest','Hungarian','348','forint (inv.)','HUF','(fillér (inv.))','Republic of Hungary','HU','HUN','Hungary','150','151',1),(352,'Reykjavik','Icelander','352','króna (pl. krónur)','ISK','','Republic of Iceland','IS','ISL','Iceland','150','154',1),(356,'New Delhi','Indian','356','Indian rupee','INR','paisa','Republic of India','IN','IND','India','142','034',0),(360,'Jakarta','Indonesian','360','Indonesian rupiah (inv.)','IDR','sen (inv.)','Republic of Indonesia','ID','IDN','Indonesia','142','035',0),(364,'Tehran','Iranian','364','Iranian rial','IRR','(dinar) (IR1)','Islamic Republic of Iran','IR','IRN','Iran, Islamic Republic of','142','034',0),(368,'Baghdad','Iraqi','368','Iraqi dinar','IQD','fils (inv.)','Republic of Iraq','IQ','IRQ','Iraq','142','145',0),(372,'Dublin','Irishman; Irishwoman','372','euro','EUR','cent','Ireland (IE1)','IE','IRL','Ireland','150','154',1),(376,'(IL1)','Israeli','376','shekel','ILS','agora','State of Israel','IL','ISR','Israel','142','145',0),(380,'Rome','Italian','380','euro','EUR','cent','Italian Republic','IT','ITA','Italy','150','039',1),(384,'Yamoussoukro (CI1)','Ivorian','384','CFA franc (BCEAO)','XOF','centime','Republic of Côte d’Ivoire','CI','CIV','Côte d\'Ivoire','002','011',0),(388,'Kingston','Jamaican','388','Jamaica dollar','JMD','cent','Jamaica','JM','JAM','Jamaica','019','029',0),(392,'Tokyo','Japanese','392','yen (inv.)','JPY','(sen (inv.)) (JP1)','Japan','JP','JPN','Japan','142','030',0),(398,'Astana','Kazakh','398','tenge (inv.)','KZT','tiyn','Republic of Kazakhstan','KZ','KAZ','Kazakhstan','142','143',0),(400,'Amman','Jordanian','400','Jordanian dinar','JOD','100 qirsh','Hashemite Kingdom of Jordan','JO','JOR','Jordan','142','145',0),(404,'Nairobi','Kenyan','404','Kenyan shilling','KES','cent','Republic of Kenya','KE','KEN','Kenya','002','014',0),(408,'Pyongyang','North Korean','408','North Korean won (inv.)','KPW','chun (inv.)','Democratic People’s Republic of Korea','KP','PRK','Korea, Democratic People\'s Republic of','142','030',0),(410,'Seoul','South Korean','410','South Korean won (inv.)','KRW','(chun (inv.))','Republic of Korea','KR','KOR','Korea, Republic of','142','030',0),(414,'Kuwait City','Kuwaiti','414','Kuwaiti dinar','KWD','fils (inv.)','State of Kuwait','KW','KWT','Kuwait','142','145',0),(417,'Bishkek','Kyrgyz','417','som','KGS','tyiyn','Kyrgyz Republic','KG','KGZ','Kyrgyzstan','142','143',0),(418,'Vientiane','Lao','418','kip (inv.)','LAK','(at (inv.))','Lao People’s Democratic Republic','LA','LAO','Lao People\'s Democratic Republic','142','035',0),(422,'Beirut','Lebanese','422','Lebanese pound','LBP','(piastre)','Lebanese Republic','LB','LBN','Lebanon','142','145',0),(426,'Maseru','Basotho','426','loti (pl. maloti)','LSL','sente','Kingdom of Lesotho','LS','LSO','Lesotho','002','018',0),(428,'Riga','Latvian','428','lats (pl. lati)','LVL','santims','Republic of Latvia','LV','LVA','Latvia','150','154',1),(430,'Monrovia','Liberian','430','Liberian dollar','LRD','cent','Republic of Liberia','LR','LBR','Liberia','002','011',0),(434,'Tripoli','Libyan','434','Libyan dinar','LYD','dirham','Socialist People’s Libyan Arab Jamahiriya','LY','LBY','Libya','002','015',0),(438,'Vaduz','Liechtensteiner','438','Swiss franc','CHF','centime','Principality of Liechtenstein','LI','LIE','Liechtenstein','150','155',1),(440,'Vilnius','Lithuanian','440','litas (pl. litai)','LTL','centas','Republic of Lithuania','LT','LTU','Lithuania','150','154',1),(442,'Luxembourg','Luxembourger','442','euro','EUR','cent','Grand Duchy of Luxembourg','LU','LUX','Luxembourg','150','155',1),(446,'Macao (MO3)','Macanese','446','pataca','MOP','avo','Macao Special Administrative Region of the People’s Republic of China (MO2)','MO','MAC','Macao','142','030',0),(450,'Antananarivo','Malagasy','450','ariary','MGA','iraimbilanja (inv.)','Republic of Madagascar','MG','MDG','Madagascar','002','014',0),(454,'Lilongwe','Malawian','454','Malawian kwacha (inv.)','MWK','tambala (inv.)','Republic of Malawi','MW','MWI','Malawi','002','014',0),(458,'Kuala Lumpur (MY1)','Malaysian','458','ringgit (inv.)','MYR','sen (inv.)','Malaysia','MY','MYS','Malaysia','142','035',0),(462,'Malé','Maldivian','462','rufiyaa','MVR','laari (inv.)','Republic of Maldives','MV','MDV','Maldives','142','034',0),(466,'Bamako','Malian','466','CFA franc (BCEAO)','XOF','centime','Republic of Mali','ML','MLI','Mali','002','011',0),(470,'Valletta','Maltese','470','euro','EUR','cent','Republic of Malta','MT','MLT','Malta','150','039',1),(474,'Fort-de-France','Martinican','474','euro','EUR','cent','Martinique','MQ','MTQ','Martinique','019','029',0),(478,'Nouakchott','Mauritanian','478','ouguiya','MRO','khoum','Islamic Republic of Mauritania','MR','MRT','Mauritania','002','011',0),(480,'Port Louis','Mauritian','480','Mauritian rupee','MUR','cent','Republic of Mauritius','MU','MUS','Mauritius','002','014',0),(484,'Mexico City','Mexican','484','Mexican peso','MXN','centavo','United Mexican States','MX','MEX','Mexico','019','013',0),(492,'Monaco','Monegasque','492','euro','EUR','cent','Principality of Monaco','MC','MCO','Monaco','150','155',0),(496,'Ulan Bator','Mongolian','496','tugrik','MNT','möngö (inv.)','Mongolia','MN','MNG','Mongolia','142','030',0),(498,'Chisinau','Moldovan','498','Moldovan leu (pl. lei)','MDL','ban','Republic of Moldova','MD','MDA','Moldova, Republic of','150','151',0),(499,'Podgorica','Montenegrin','499','euro','EUR','cent','Montenegro','ME','MNE','Montenegro','150','039',0),(500,'Plymouth (MS2)','Montserratian','500','East Caribbean dollar','XCD','cent','Montserrat','MS','MSR','Montserrat','019','029',0),(504,'Rabat','Moroccan','504','Moroccan dirham','MAD','centime','Kingdom of Morocco','MA','MAR','Morocco','002','015',0),(508,'Maputo','Mozambican','508','metical','MZN','centavo','Republic of Mozambique','MZ','MOZ','Mozambique','002','014',0),(512,'Muscat','Omani','512','Omani rial','OMR','baiza','Sultanate of Oman','OM','OMN','Oman','142','145',0),(516,'Windhoek','Namibian','516','Namibian dollar','NAD','cent','Republic of Namibia','NA','NAM','Namibia','002','018',0),(520,'Yaren','Nauruan','520','Australian dollar','AUD','cent','Republic of Nauru','NR','NRU','Nauru','009','057',0),(524,'Kathmandu','Nepalese','524','Nepalese rupee','NPR','paisa (inv.)','Nepal','NP','NPL','Nepal','142','034',0),(528,'Amsterdam (NL2)','Dutch','528','euro','EUR','cent','Kingdom of the Netherlands','NL','NLD','Netherlands','150','155',1),(531,'Willemstad','Curaçaoan','531','Netherlands Antillean guilder (CW1)','ANG','cent','Curaçao','CW','CUW','Curaçao','019','029',0),(533,'Oranjestad','Aruban','533','Aruban guilder','AWG','cent','Aruba','AW','ABW','Aruba','019','029',0),(534,'Philipsburg','Sint Maartener','534','Netherlands Antillean guilder (SX1)','ANG','cent','Sint Maarten','SX','SXM','Sint Maarten (Dutch part)','019','029',0),(535,NULL,'of Bonaire, Sint Eustatius and Saba','535',NULL,NULL,NULL,NULL,'BQ','BES','Bonaire, Sint Eustatius and Saba','019','029',0),(540,'Nouméa','New Caledonian','540','CFP franc','XPF','centime','New Caledonia','NC','NCL','New Caledonia','009','054',0),(548,'Port Vila','Vanuatuan','548','vatu (inv.)','VUV','','Republic of Vanuatu','VU','VUT','Vanuatu','009','054',0),(554,'Wellington','New Zealander','554','New Zealand dollar','NZD','cent','New Zealand','NZ','NZL','New Zealand','009','053',0),(558,'Managua','Nicaraguan','558','córdoba oro','NIO','centavo','Republic of Nicaragua','NI','NIC','Nicaragua','019','013',0),(562,'Niamey','Nigerien','562','CFA franc (BCEAO)','XOF','centime','Republic of Niger','NE','NER','Niger','002','011',0),(566,'Abuja','Nigerian','566','naira (inv.)','NGN','kobo (inv.)','Federal Republic of Nigeria','NG','NGA','Nigeria','002','011',0),(570,'Alofi','Niuean','570','New Zealand dollar','NZD','cent','Niue','NU','NIU','Niue','009','061',0),(574,'Kingston','Norfolk Islander','574','Australian dollar','AUD','cent','Territory of Norfolk Island','NF','NFK','Norfolk Island','009','053',0),(578,'Oslo','Norwegian','578','Norwegian krone (pl. kroner)','NOK','øre (inv.)','Kingdom of Norway','NO','NOR','Norway','150','154',1),(580,'Saipan','Northern Mariana Islander','580','US dollar','USD','cent','Commonwealth of the Northern Mariana Islands','MP','MNP','Northern Mariana Islands','009','057',0),(581,'United States Minor Outlying Islands','of United States Minor Outlying Islands','581','US dollar','USD','cent','United States Minor Outlying Islands','UM','UMI','United States Minor Outlying Islands','','',0),(583,'Palikir','Micronesian','583','US dollar','USD','cent','Federated States of Micronesia','FM','FSM','Micronesia, Federated States of','009','057',0),(584,'Majuro','Marshallese','584','US dollar','USD','cent','Republic of the Marshall Islands','MH','MHL','Marshall Islands','009','057',0),(585,'Melekeok','Palauan','585','US dollar','USD','cent','Republic of Palau','PW','PLW','Palau','009','057',0),(586,'Islamabad','Pakistani','586','Pakistani rupee','PKR','paisa','Islamic Republic of Pakistan','PK','PAK','Pakistan','142','034',0),(591,'Panama City','Panamanian','591','balboa','PAB','centésimo','Republic of Panama','PA','PAN','Panama','019','013',0),(598,'Port Moresby','Papua New Guinean','598','kina (inv.)','PGK','toea (inv.)','Independent State of Papua New Guinea','PG','PNG','Papua New Guinea','009','054',0),(600,'Asunción','Paraguayan','600','guaraní','PYG','céntimo','Republic of Paraguay','PY','PRY','Paraguay','019','005',0),(604,'Lima','Peruvian','604','new sol','PEN','céntimo','Republic of Peru','PE','PER','Peru','019','005',0),(608,'Manila','Filipino','608','Philippine peso','PHP','centavo','Republic of the Philippines','PH','PHL','Philippines','142','035',0),(612,'Adamstown','Pitcairner','612','New Zealand dollar','NZD','cent','Pitcairn Islands','PN','PCN','Pitcairn','009','061',0),(616,'Warsaw','Pole','616','zloty','PLN','grosz (pl. groszy)','Republic of Poland','PL','POL','Poland','150','151',1),(620,'Lisbon','Portuguese','620','euro','EUR','cent','Portuguese Republic','PT','PRT','Portugal','150','039',1),(624,'Bissau','Guinea-Bissau national','624','CFA franc (BCEAO)','XOF','centime','Republic of Guinea-Bissau','GW','GNB','Guinea-Bissau','002','011',0),(626,'Dili','East Timorese','626','US dollar','USD','cent','Democratic Republic of East Timor','TL','TLS','Timor-Leste','142','035',0),(630,'San Juan','Puerto Rican','630','US dollar','USD','cent','Commonwealth of Puerto Rico','PR','PRI','Puerto Rico','019','029',0),(634,'Doha','Qatari','634','Qatari riyal','QAR','dirham','State of Qatar','QA','QAT','Qatar','142','145',0),(638,'Saint-Denis','Reunionese','638','euro','EUR','cent','Réunion','RE','REU','Réunion','002','014',0),(642,'Bucharest','Romanian','642','Romanian leu (pl. lei)','RON','ban (pl. bani)','Romania','RO','ROU','Romania','150','151',1),(643,'Moscow','Russian','643','Russian rouble','RUB','kopek','Russian Federation','RU','RUS','Russian Federation','150','151',0),(646,'Kigali','Rwandan; Rwandese','646','Rwandese franc','RWF','centime','Republic of Rwanda','RW','RWA','Rwanda','002','014',0),(652,'Gustavia','of Saint Barthélemy','652','euro','EUR','cent','Collectivity of Saint Barthélemy','BL','BLM','Saint Barthélemy','019','029',0),(654,'Jamestown','Saint Helenian','654','Saint Helena pound','SHP','penny','Saint Helena, Ascension and Tristan da Cunha','SH','SHN','Saint Helena, Ascension and Tristan da Cunha','002','011',0),(659,'Basseterre','Kittsian; Nevisian','659','East Caribbean dollar','XCD','cent','Federation of Saint Kitts and Nevis','KN','KNA','Saint Kitts and Nevis','019','029',0),(660,'The Valley','Anguillan','660','East Caribbean dollar','XCD','cent','Anguilla','AI','AIA','Anguilla','019','029',0),(662,'Castries','Saint Lucian','662','East Caribbean dollar','XCD','cent','Saint Lucia','LC','LCA','Saint Lucia','019','029',0),(663,'Marigot','of Saint Martin','663','euro','EUR','cent','Collectivity of Saint Martin','MF','MAF','Saint Martin (French part)','019','029',0),(666,'Saint-Pierre','St-Pierrais; Miquelonnais','666','euro','EUR','cent','Territorial Collectivity of Saint Pierre and Miquelon','PM','SPM','Saint Pierre and Miquelon','019','021',0),(670,'Kingstown','Vincentian','670','East Caribbean dollar','XCD','cent','Saint Vincent and the Grenadines','VC','VCT','Saint Vincent and the Grenadines','019','029',0),(674,'San Marino','San Marinese','674','euro','EUR ','cent','Republic of San Marino','SM','SMR','San Marino','150','039',0),(678,'São Tomé','São Toméan','678','dobra','STD','centavo','Democratic Republic of São Tomé and Príncipe','ST','STP','Sao Tome and Principe','002','017',0),(682,'Riyadh','Saudi Arabian','682','riyal','SAR','halala','Kingdom of Saudi Arabia','SA','SAU','Saudi Arabia','142','145',0),(686,'Dakar','Senegalese','686','CFA franc (BCEAO)','XOF','centime','Republic of Senegal','SN','SEN','Senegal','002','011',0),(688,'Belgrade','Serb','688','Serbian dinar','RSD','para (inv.)','Republic of Serbia','RS','SRB','Serbia','150','039',0),(690,'Victoria','Seychellois','690','Seychelles rupee','SCR','cent','Republic of Seychelles','SC','SYC','Seychelles','002','014',0),(694,'Freetown','Sierra Leonean','694','leone','SLL','cent','Republic of Sierra Leone','SL','SLE','Sierra Leone','002','011',0),(702,'Singapore','Singaporean','702','Singapore dollar','SGD','cent','Republic of Singapore','SG','SGP','Singapore','142','035',0),(703,'Bratislava','Slovak','703','euro','EUR','cent','Slovak Republic','SK','SVK','Slovakia','150','151',1),(704,'Hanoi','Vietnamese','704','dong','VND','(10 hào','Socialist Republic of Vietnam','VN','VNM','Viet Nam','142','035',0),(705,'Ljubljana','Slovene','705','euro','EUR','cent','Republic of Slovenia','SI','SVN','Slovenia','150','039',1),(706,'Mogadishu','Somali','706','Somali shilling','SOS','cent','Somali Republic','SO','SOM','Somalia','002','014',0),(710,'Pretoria (ZA1)','South African','710','rand','ZAR','cent','Republic of South Africa','ZA','ZAF','South Africa','002','018',0),(716,'Harare','Zimbabwean','716','Zimbabwe dollar (ZW1)','ZWL','cent','Republic of Zimbabwe','ZW','ZWE','Zimbabwe','002','014',0),(724,'Madrid','Spaniard','724','euro','EUR','cent','Kingdom of Spain','ES','ESP','Spain','150','039',1),(728,NULL,'South Sudanese','728',NULL,NULL,NULL,NULL,'SS','SSD','South Sudan','002','015',0),(729,'Khartoum','Sudanese','729','Sudanese pound','SDG','piastre','Republic of the Sudan','SD','SDN','Sudan','002','015',0),(732,'Al aaiun','Sahrawi','732','Moroccan dirham','MAD','centime','Western Sahara','EH','ESH','Western Sahara','002','015',0),(740,'Paramaribo','Surinamer','740','Surinamese dollar','SRD','cent','Republic of Suriname','SR','SUR','Suriname','019','005',0),(744,'Longyearbyen','of Svalbard','744','Norwegian krone (pl. kroner)','NOK','øre (inv.)','Svalbard and Jan Mayen','SJ','SJM','Svalbard and Jan Mayen','150','154',0),(748,'Mbabane','Swazi','748','lilangeni','SZL','cent','Kingdom of Swaziland','SZ','SWZ','Swaziland','002','018',0),(752,'Stockholm','Swede','752','krona (pl. kronor)','SEK','öre (inv.)','Kingdom of Sweden','SE','SWE','Sweden','150','154',1),(756,'Berne','Swiss','756','Swiss franc','CHF','centime','Swiss Confederation','CH','CHE','Switzerland','150','155',1),(760,'Damascus','Syrian','760','Syrian pound','SYP','piastre','Syrian Arab Republic','SY','SYR','Syrian Arab Republic','142','145',0),(762,'Dushanbe','Tajik','762','somoni','TJS','diram','Republic of Tajikistan','TJ','TJK','Tajikistan','142','143',0),(764,'Bangkok','Thai','764','baht (inv.)','THB','satang (inv.)','Kingdom of Thailand','TH','THA','Thailand','142','035',0),(768,'Lomé','Togolese','768','CFA franc (BCEAO)','XOF','centime','Togolese Republic','TG','TGO','Togo','002','011',0),(772,'(TK2)','Tokelauan','772','New Zealand dollar','NZD','cent','Tokelau','TK','TKL','Tokelau','009','061',0),(776,'Nuku’alofa','Tongan','776','pa’anga (inv.)','TOP','seniti (inv.)','Kingdom of Tonga','TO','TON','Tonga','009','061',0),(780,'Port of Spain','Trinidadian; Tobagonian','780','Trinidad and Tobago dollar','TTD','cent','Republic of Trinidad and Tobago','TT','TTO','Trinidad and Tobago','019','029',0),(784,'Abu Dhabi','Emirian','784','UAE dirham','AED','fils (inv.)','United Arab Emirates','AE','ARE','United Arab Emirates','142','145',0),(788,'Tunis','Tunisian','788','Tunisian dinar','TND','millime','Republic of Tunisia','TN','TUN','Tunisia','002','015',0),(792,'Ankara','Turk','792','Turkish lira (inv.)','TRY','kurus (inv.)','Republic of Turkey','TR','TUR','Turkey','142','145',0),(795,'Ashgabat','Turkmen','795','Turkmen manat (inv.)','TMT','tenge (inv.)','Turkmenistan','TM','TKM','Turkmenistan','142','143',0),(796,'Cockburn Town','Turks and Caicos Islander','796','US dollar','USD','cent','Turks and Caicos Islands','TC','TCA','Turks and Caicos Islands','019','029',0),(798,'Funafuti','Tuvaluan','798','Australian dollar','AUD','cent','Tuvalu','TV','TUV','Tuvalu','009','061',0),(800,'Kampala','Ugandan','800','Uganda shilling','UGX','cent','Republic of Uganda','UG','UGA','Uganda','002','014',0),(804,'Kiev','Ukrainian','804','hryvnia','UAH','kopiyka','Ukraine','UA','UKR','Ukraine','150','151',0),(807,'Skopje','of the former Yugoslav Republic of Macedonia','807','denar (pl. denars)','MKD','deni (inv.)','the former Yugoslav Republic of Macedonia','MK','MKD','Macedonia, the former Yugoslav Republic of','150','039',0),(818,'Cairo','Egyptian','818','Egyptian pound','EGP','piastre','Arab Republic of Egypt','EG','EGY','Egypt','002','015',0),(826,'London','Briton','826','pound sterling','GBP','penny (pl. pence)','United Kingdom of Great Britain and Northern Ireland','GB','GBR','United Kingdom','150','154',1),(831,'St Peter Port','of Guernsey','831','Guernsey pound (GG2)','GGP (GG2)','penny (pl. pence)','Bailiwick of Guernsey','GG','GGY','Guernsey','150','154',0),(832,'St Helier','of Jersey','832','Jersey pound (JE2)','JEP (JE2)','penny (pl. pence)','Bailiwick of Jersey','JE','JEY','Jersey','150','154',0),(833,'Douglas','Manxman; Manxwoman','833','Manx pound (IM2)','IMP (IM2)','penny (pl. pence)','Isle of Man','IM','IMN','Isle of Man','150','154',0),(834,'Dodoma (TZ1)','Tanzanian','834','Tanzanian shilling','TZS','cent','United Republic of Tanzania','TZ','TZA','Tanzania, United Republic of','002','014',0),(840,'Washington DC','American','840','US dollar','USD','cent','United States of America','US','USA','United States','019','021',0),(850,'Charlotte Amalie','US Virgin Islander','850','US dollar','USD','cent','United States Virgin Islands','VI','VIR','Virgin Islands, U.S.','019','029',0),(854,'Ouagadougou','Burkinabe','854','CFA franc (BCEAO)','XOF','centime','Burkina Faso','BF','BFA','Burkina Faso','002','011',0),(858,'Montevideo','Uruguayan','858','Uruguayan peso','UYU','centésimo','Eastern Republic of Uruguay','UY','URY','Uruguay','019','005',0),(860,'Tashkent','Uzbek','860','sum (inv.)','UZS','tiyin (inv.)','Republic of Uzbekistan','UZ','UZB','Uzbekistan','142','143',0),(862,'Caracas','Venezuelan','862','bolívar fuerte (pl. bolívares fuertes)','VEF','céntimo','Bolivarian Republic of Venezuela','VE','VEN','Venezuela, Bolivarian Republic of','019','005',0),(876,'Mata-Utu','Wallisian; Futunan; Wallis and Futuna Islander','876','CFP franc','XPF','centime','Wallis and Futuna','WF','WLF','Wallis and Futuna','009','061',0),(882,'Apia','Samoan','882','tala (inv.)','WST','sene (inv.)','Independent State of Samoa','WS','WSM','Samoa','009','061',0),(887,'San’a','Yemenite','887','Yemeni rial','YER','fils (inv.)','Republic of Yemen','YE','YEM','Yemen','142','145',0),(894,'Lusaka','Zambian','894','Zambian kwacha (inv.)','ZMK','ngwee (inv.)','Republic of Zambia','ZM','ZMB','Zambia','002','014',0);
/*!40000 ALTER TABLE `countries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `credits`
--

DROP TABLE IF EXISTS `credits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `credits` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL,
  `amount` decimal(13,4) NOT NULL,
  `balance` decimal(13,4) NOT NULL,
  `credit_date` date DEFAULT NULL,
  `credit_number` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `private_notes` text COLLATE utf8_unicode_ci NOT NULL,
  `public_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credits_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `credits_user_id_foreign` (`user_id`),
  KEY `credits_account_id_index` (`account_id`),
  KEY `credits_client_id_index` (`client_id`),
  KEY `credits_public_id_index` (`public_id`),
  CONSTRAINT `credits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `credits_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `credits_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `credits`
--

LOCK TABLES `credits` WRITE;
/*!40000 ALTER TABLE `credits` DISABLE KEYS */;
/*!40000 ALTER TABLE `credits` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `currencies`
--

DROP TABLE IF EXISTS `currencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `currencies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `symbol` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `precision` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `thousand_separator` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `decimal_separator` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `currencies`
--

LOCK TABLES `currencies` WRITE;
/*!40000 ALTER TABLE `currencies` DISABLE KEYS */;
INSERT INTO `currencies` VALUES (1,'US Dollar','$','2',',','.','USD'),(2,'Pound Sterling','£','2',',','.','GBP'),(3,'Euro','€','2',',','.','EUR');
/*!40000 ALTER TABLE `currencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `date_formats`
--

DROP TABLE IF EXISTS `date_formats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `date_formats` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `format` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `picker_format` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `date_formats`
--

LOCK TABLES `date_formats` WRITE;
/*!40000 ALTER TABLE `date_formats` DISABLE KEYS */;
INSERT INTO `date_formats` VALUES (1,'d/M/Y','dd/M/yyyy','10/Mar/2013'),(2,'d-M-Y','dd-M-yyyy','10-Mar-2013'),(3,'d/F/Y','dd/MM/yyyy','10/March/2013'),(4,'d-F-Y','dd-MM-yyyy','10-March-2013'),(5,'M j, Y','M d, yyyy','Mar 10, 2013'),(6,'F j, Y','MM d, yyyy','March 10, 2013'),(7,'D M j, Y','D MM d, yyyy','Mon March 10, 2013');
/*!40000 ALTER TABLE `date_formats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `datetime_formats`
--

DROP TABLE IF EXISTS `datetime_formats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `datetime_formats` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `format` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `datetime_formats`
--

LOCK TABLES `datetime_formats` WRITE;
/*!40000 ALTER TABLE `datetime_formats` DISABLE KEYS */;
INSERT INTO `datetime_formats` VALUES (1,'d/M/Y g:i a','10/Mar/2013'),(2,'d-M-Yk g:i a','10-Mar-2013'),(3,'d/F/Y g:i a','10/March/2013'),(4,'d-F-Y g:i a','10-March-2013'),(5,'M j, Y g:i a','Mar 10, 2013 6:15 pm'),(6,'F j, Y g:i a','March 10, 2013 6:15 pm'),(7,'D M jS, Y g:ia','Mon March 10th, 2013 6:15 pm');
/*!40000 ALTER TABLE `datetime_formats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `frequencies`
--

DROP TABLE IF EXISTS `frequencies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `frequencies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `frequencies`
--

LOCK TABLES `frequencies` WRITE;
/*!40000 ALTER TABLE `frequencies` DISABLE KEYS */;
INSERT INTO `frequencies` VALUES (1,'Weekly'),(2,'Two weeks'),(3,'Four weeks'),(4,'Monthly'),(5,'Three months'),(6,'Six months'),(7,'Annually');
/*!40000 ALTER TABLE `frequencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gateways`
--

DROP TABLE IF EXISTS `gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gateways` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `provider` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gateways`
--

LOCK TABLES `gateways` WRITE;
/*!40000 ALTER TABLE `gateways` DISABLE KEYS */;
INSERT INTO `gateways` VALUES (1,'0000-00-00 00:00:00','0000-00-00 00:00:00','Authorize.Net AIM','AuthorizeNet_AIM',1),(2,'0000-00-00 00:00:00','0000-00-00 00:00:00','Authorize.Net SIM','AuthorizeNet_SIM',1),(3,'0000-00-00 00:00:00','0000-00-00 00:00:00','CardSave','CardSave',1),(4,'0000-00-00 00:00:00','0000-00-00 00:00:00','Eway Rapid','Eway_Rapid',1),(5,'0000-00-00 00:00:00','0000-00-00 00:00:00','FirstData Connect','FirstData_Connect',1),(6,'0000-00-00 00:00:00','0000-00-00 00:00:00','GoCardless','GoCardless',1),(7,'0000-00-00 00:00:00','0000-00-00 00:00:00','Migs ThreeParty','Migs_ThreeParty',1),(8,'0000-00-00 00:00:00','0000-00-00 00:00:00','Migs TwoParty','Migs_TwoParty',1),(9,'0000-00-00 00:00:00','0000-00-00 00:00:00','Mollie','Mollie',1),(10,'0000-00-00 00:00:00','0000-00-00 00:00:00','MultiSafepay','MultiSafepay',1),(11,'0000-00-00 00:00:00','0000-00-00 00:00:00','Netaxept','Netaxept',1),(12,'0000-00-00 00:00:00','0000-00-00 00:00:00','NetBanx','NetBanx',1),(13,'0000-00-00 00:00:00','0000-00-00 00:00:00','PayFast','PayFast',1),(14,'0000-00-00 00:00:00','0000-00-00 00:00:00','Payflow Pro','Payflow_Pro',1),(15,'0000-00-00 00:00:00','0000-00-00 00:00:00','PaymentExpress PxPay','PaymentExpress_PxPay',1),(16,'0000-00-00 00:00:00','0000-00-00 00:00:00','PaymentExpress PxPost','PaymentExpress_PxPost',1),(17,'0000-00-00 00:00:00','0000-00-00 00:00:00','PayPal Express','PayPal_Express',1),(18,'0000-00-00 00:00:00','0000-00-00 00:00:00','PayPal Pro','PayPal_Pro',1),(19,'0000-00-00 00:00:00','0000-00-00 00:00:00','Pin','Pin',1),(20,'0000-00-00 00:00:00','0000-00-00 00:00:00','SagePay Direct','SagePay_Direct',1),(21,'0000-00-00 00:00:00','0000-00-00 00:00:00','SagePay Server','SagePay_Server',1),(22,'0000-00-00 00:00:00','0000-00-00 00:00:00','SecurePay DirectPost','SecurePay_DirectPost',1),(23,'0000-00-00 00:00:00','0000-00-00 00:00:00','Stripe','Stripe',1),(24,'0000-00-00 00:00:00','0000-00-00 00:00:00','TargetPay Direct eBanking','TargetPay_Directebanking',1),(25,'0000-00-00 00:00:00','0000-00-00 00:00:00','TargetPay Ideal','TargetPay_Ideal',1),(26,'0000-00-00 00:00:00','0000-00-00 00:00:00','TargetPay Mr Cash','TargetPay_Mrcash',1),(27,'0000-00-00 00:00:00','0000-00-00 00:00:00','TwoCheckout','TwoCheckout',1),(28,'0000-00-00 00:00:00','0000-00-00 00:00:00','WorldPay','WorldPay',1);
/*!40000 ALTER TABLE `gateways` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `industries`
--

DROP TABLE IF EXISTS `industries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `industries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `industries`
--

LOCK TABLES `industries` WRITE;
/*!40000 ALTER TABLE `industries` DISABLE KEYS */;
INSERT INTO `industries` VALUES (1,'Accounting & Legal'),(2,'Advertising'),(3,'Aerospace'),(4,'Agriculture'),(5,'Automotive'),(6,'Banking & Finance'),(7,'Biotechnology'),(8,'Broadcasting'),(9,'Business Services'),(10,'Commodities & Chemicals'),(11,'Communications'),(12,'Computers & Hightech'),(13,'Defense'),(14,'Energy'),(15,'Entertainment'),(16,'Government'),(17,'Healthcare & Life Sciences'),(18,'Insurance'),(19,'Manufacturing'),(20,'Marketing'),(21,'Media'),(22,'Nonprofit & Higher Ed'),(23,'Pharmaceuticals'),(24,'Professional Services & Consulting'),(25,'Real Estate'),(26,'Retail & Wholesale'),(27,'Sports'),(28,'Transportation'),(29,'Travel & Luxury');
/*!40000 ALTER TABLE `industries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invitations`
--

DROP TABLE IF EXISTS `invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invitations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `contact_id` int(10) unsigned NOT NULL,
  `invoice_id` int(10) unsigned NOT NULL,
  `invitation_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `transaction_reference` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `sent_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `viewed_date` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `public_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invitations_account_id_public_id_unique` (`account_id`,`public_id`),
  UNIQUE KEY `invitations_invitation_key_unique` (`invitation_key`),
  KEY `invitations_user_id_foreign` (`user_id`),
  KEY `invitations_contact_id_foreign` (`contact_id`),
  KEY `invitations_invoice_id_index` (`invoice_id`),
  KEY `invitations_public_id_index` (`public_id`),
  CONSTRAINT `invitations_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invitations_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invitations_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invitations`
--

LOCK TABLES `invitations` WRITE;
/*!40000 ALTER TABLE `invitations` DISABLE KEYS */;
/*!40000 ALTER TABLE `invitations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_items`
--

DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `invoice_id` int(10) unsigned NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `product_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `notes` text COLLATE utf8_unicode_ci NOT NULL,
  `cost` decimal(13,4) NOT NULL,
  `qty` decimal(13,4) NOT NULL,
  `tax_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `tax_rate` decimal(13,4) NOT NULL,
  `public_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_items_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `invoice_items_product_id_foreign` (`product_id`),
  KEY `invoice_items_user_id_foreign` (`user_id`),
  KEY `invoice_items_invoice_id_index` (`invoice_id`),
  CONSTRAINT `invoice_items_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_items_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_items`
--

LOCK TABLES `invoice_items` WRITE;
/*!40000 ALTER TABLE `invoice_items` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoice_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoice_statuses`
--

DROP TABLE IF EXISTS `invoice_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_statuses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_statuses`
--

LOCK TABLES `invoice_statuses` WRITE;
/*!40000 ALTER TABLE `invoice_statuses` DISABLE KEYS */;
INSERT INTO `invoice_statuses` VALUES (1,'Draft'),(2,'Sent'),(3,'Viewed'),(4,'Partial'),(5,'Paid');
/*!40000 ALTER TABLE `invoice_statuses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `invoices`
--

DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `client_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `invoice_status_id` int(10) unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `invoice_number` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `discount` float(8,2) NOT NULL,
  `po_number` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `invoice_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `terms` text COLLATE utf8_unicode_ci NOT NULL,
  `public_notes` text COLLATE utf8_unicode_ci NOT NULL,
  `is_deleted` tinyint(1) NOT NULL,
  `is_recurring` tinyint(1) NOT NULL,
  `frequency_id` int(10) unsigned NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `last_sent_date` timestamp NULL DEFAULT NULL,
  `recurring_invoice_id` int(10) unsigned DEFAULT NULL,
  `tax_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `tax_rate` decimal(13,4) NOT NULL,
  `amount` decimal(13,4) NOT NULL,
  `balance` decimal(13,4) NOT NULL,
  `public_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_account_id_public_id_unique` (`account_id`,`public_id`),
  UNIQUE KEY `invoices_account_id_invoice_number_unique` (`account_id`,`invoice_number`),
  KEY `invoices_user_id_foreign` (`user_id`),
  KEY `invoices_invoice_status_id_foreign` (`invoice_status_id`),
  KEY `invoices_client_id_index` (`client_id`),
  KEY `invoices_account_id_index` (`account_id`),
  KEY `invoices_recurring_invoice_id_index` (`recurring_invoice_id`),
  KEY `invoices_public_id_index` (`public_id`),
  CONSTRAINT `invoices_recurring_invoice_id_foreign` FOREIGN KEY (`recurring_invoice_id`) REFERENCES `invoices` (`id`),
  CONSTRAINT `invoices_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`),
  CONSTRAINT `invoices_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoices_invoice_status_id_foreign` FOREIGN KEY (`invoice_status_id`) REFERENCES `invoice_statuses` (`id`),
  CONSTRAINT `invoices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoices`
--

LOCK TABLES `invoices` WRITE;
/*!40000 ALTER TABLE `invoices` DISABLE KEYS */;
/*!40000 ALTER TABLE `invoices` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `migration` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `migrations`
--

LOCK TABLES `migrations` WRITE;
/*!40000 ALTER TABLE `migrations` DISABLE KEYS */;
INSERT INTO `migrations` VALUES ('2013_11_05_180133_confide_setup_users_table',1),('2013_11_28_195703_setup_countries_table',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_reminders`
--

DROP TABLE IF EXISTS `password_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reminders` (
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `token` varchar(255) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_reminders`
--

LOCK TABLES `password_reminders` WRITE;
/*!40000 ALTER TABLE `password_reminders` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_reminders` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_terms`
--

DROP TABLE IF EXISTS `payment_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_terms` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `num_days` int(11) NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_terms`
--

LOCK TABLES `payment_terms` WRITE;
/*!40000 ALTER TABLE `payment_terms` DISABLE KEYS */;
INSERT INTO `payment_terms` VALUES (1,7,'Net 7'),(2,10,'Net 10'),(3,14,'Net 14'),(4,15,'Net 15'),(5,30,'Net 30'),(6,60,'Net 60');
/*!40000 ALTER TABLE `payment_terms` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_types`
--

DROP TABLE IF EXISTS `payment_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_types`
--

LOCK TABLES `payment_types` WRITE;
/*!40000 ALTER TABLE `payment_types` DISABLE KEYS */;
INSERT INTO `payment_types` VALUES (1,'Apply Credit'),(2,'Bank Transfer'),(3,'Cash'),(4,'Debit'),(5,'ACH'),(6,'Visa Card'),(7,'MasterCard'),(8,'American Express'),(9,'Discover Card'),(10,'Diners Card'),(11,'EuroCard'),(12,'Nova'),(13,'Credit Card Other'),(14,'PayPal'),(15,'Google Wallet');
/*!40000 ALTER TABLE `payment_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payments`
--

DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payments` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `contact_id` int(10) unsigned DEFAULT NULL,
  `invitation_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `account_gateway_id` int(10) unsigned DEFAULT NULL,
  `payment_type_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL,
  `amount` decimal(13,4) NOT NULL,
  `payment_date` date NOT NULL,
  `transaction_reference` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `payer_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `public_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `payments_invoice_id_foreign` (`invoice_id`),
  KEY `payments_contact_id_foreign` (`contact_id`),
  KEY `payments_account_gateway_id_foreign` (`account_gateway_id`),
  KEY `payments_user_id_foreign` (`user_id`),
  KEY `payments_payment_type_id_foreign` (`payment_type_id`),
  KEY `payments_account_id_index` (`account_id`),
  KEY `payments_client_id_index` (`client_id`),
  KEY `payments_public_id_index` (`public_id`),
  CONSTRAINT `payments_payment_type_id_foreign` FOREIGN KEY (`payment_type_id`) REFERENCES `payment_types` (`id`),
  CONSTRAINT `payments_account_gateway_id_foreign` FOREIGN KEY (`account_gateway_id`) REFERENCES `account_gateways` (`id`),
  CONSTRAINT `payments_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`),
  CONSTRAINT `payments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`),
  CONSTRAINT `payments_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payments`
--

LOCK TABLES `payments` WRITE;
/*!40000 ALTER TABLE `payments` DISABLE KEYS */;
/*!40000 ALTER TABLE `payments` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `products` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `product_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `notes` text COLLATE utf8_unicode_ci NOT NULL,
  `cost` decimal(13,4) NOT NULL,
  `qty` decimal(13,4) NOT NULL,
  `public_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `products_user_id_foreign` (`user_id`),
  KEY `products_account_id_index` (`account_id`),
  CONSTRAINT `products_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `products`
--

LOCK TABLES `products` WRITE;
/*!40000 ALTER TABLE `products` DISABLE KEYS */;
/*!40000 ALTER TABLE `products` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sizes`
--

DROP TABLE IF EXISTS `sizes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sizes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sizes`
--

LOCK TABLES `sizes` WRITE;
/*!40000 ALTER TABLE `sizes` DISABLE KEYS */;
INSERT INTO `sizes` VALUES (1,'1 = 3'),(2,'4 - 10'),(3,'11 - 50'),(4,'51 - 100'),(5,'101 - 500'),(6,'500+');
/*!40000 ALTER TABLE `sizes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tax_rates`
--

DROP TABLE IF EXISTS `tax_rates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tax_rates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `rate` decimal(13,4) NOT NULL,
  `public_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_rates_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `tax_rates_user_id_foreign` (`user_id`),
  KEY `tax_rates_account_id_index` (`account_id`),
  CONSTRAINT `tax_rates_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tax_rates_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tax_rates`
--

LOCK TABLES `tax_rates` WRITE;
/*!40000 ALTER TABLE `tax_rates` DISABLE KEYS */;
/*!40000 ALTER TABLE `tax_rates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `themes`
--

DROP TABLE IF EXISTS `themes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `themes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `themes`
--

LOCK TABLES `themes` WRITE;
/*!40000 ALTER TABLE `themes` DISABLE KEYS */;
INSERT INTO `themes` VALUES (1,'amelia'),(2,'cerulean'),(3,'cosmo'),(4,'cyborg'),(5,'flatly'),(6,'journal'),(7,'readable'),(8,'simplex'),(9,'slate'),(10,'spacelab'),(11,'united'),(12,'yeti');
/*!40000 ALTER TABLE `themes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `timezones`
--

DROP TABLE IF EXISTS `timezones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timezones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `location` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=113 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timezones`
--

LOCK TABLES `timezones` WRITE;
/*!40000 ALTER TABLE `timezones` DISABLE KEYS */;
INSERT INTO `timezones` VALUES (1,'Pacific/Midway','(GMT-11:00) Midway Island'),(2,'US/Samoa','(GMT-11:00) Samoa'),(3,'US/Hawaii','(GMT-10:00) Hawaii'),(4,'US/Alaska','(GMT-09:00) Alaska'),(5,'US/Pacific','(GMT-08:00) Pacific Time (US &amp; Canada)'),(6,'America/Tijuana','(GMT-08:00) Tijuana'),(7,'US/Arizona','(GMT-07:00) Arizona'),(8,'US/Mountain','(GMT-07:00) Mountain Time (US &amp; Canada)'),(9,'America/Chihuahua','(GMT-07:00) Chihuahua'),(10,'America/Mazatlan','(GMT-07:00) Mazatlan'),(11,'America/Mexico_City','(GMT-06:00) Mexico City'),(12,'America/Monterrey','(GMT-06:00) Monterrey'),(13,'Canada/Saskatchewan','(GMT-06:00) Saskatchewan'),(14,'US/Central','(GMT-06:00) Central Time (US &amp; Canada)'),(15,'US/Eastern','(GMT-05:00) Eastern Time (US &amp; Canada)'),(16,'US/East-Indiana','(GMT-05:00) Indiana (East)'),(17,'America/Bogota','(GMT-05:00) Bogota'),(18,'America/Lima','(GMT-05:00) Lima'),(19,'America/Caracas','(GMT-04:30) Caracas'),(20,'Canada/Atlantic','(GMT-04:00) Atlantic Time (Canada)'),(21,'America/La_Paz','(GMT-04:00) La Paz'),(22,'America/Santiago','(GMT-04:00) Santiago'),(23,'Canada/Newfoundland','(GMT-03:30) Newfoundland'),(24,'America/Buenos_Aires','(GMT-03:00) Buenos Aires'),(25,'Greenland','(GMT-03:00) Greenland'),(26,'Atlantic/Stanley','(GMT-02:00) Stanley'),(27,'Atlantic/Azores','(GMT-01:00) Azores'),(28,'Atlantic/Cape_Verde','(GMT-01:00) Cape Verde Is.'),(29,'Africa/Casablanca','(GMT) Casablanca'),(30,'Europe/Dublin','(GMT) Dublin'),(31,'Europe/Lisbon','(GMT) Lisbon'),(32,'Europe/London','(GMT) London'),(33,'Africa/Monrovia','(GMT) Monrovia'),(34,'Europe/Amsterdam','(GMT+01:00) Amsterdam'),(35,'Europe/Belgrade','(GMT+01:00) Belgrade'),(36,'Europe/Berlin','(GMT+01:00) Berlin'),(37,'Europe/Bratislava','(GMT+01:00) Bratislava'),(38,'Europe/Brussels','(GMT+01:00) Brussels'),(39,'Europe/Budapest','(GMT+01:00) Budapest'),(40,'Europe/Copenhagen','(GMT+01:00) Copenhagen'),(41,'Europe/Ljubljana','(GMT+01:00) Ljubljana'),(42,'Europe/Madrid','(GMT+01:00) Madrid'),(43,'Europe/Paris','(GMT+01:00) Paris'),(44,'Europe/Prague','(GMT+01:00) Prague'),(45,'Europe/Rome','(GMT+01:00) Rome'),(46,'Europe/Sarajevo','(GMT+01:00) Sarajevo'),(47,'Europe/Skopje','(GMT+01:00) Skopje'),(48,'Europe/Stockholm','(GMT+01:00) Stockholm'),(49,'Europe/Vienna','(GMT+01:00) Vienna'),(50,'Europe/Warsaw','(GMT+01:00) Warsaw'),(51,'Europe/Zagreb','(GMT+01:00) Zagreb'),(52,'Europe/Athens','(GMT+02:00) Athens'),(53,'Europe/Bucharest','(GMT+02:00) Bucharest'),(54,'Africa/Cairo','(GMT+02:00) Cairo'),(55,'Africa/Harare','(GMT+02:00) Harare'),(56,'Europe/Helsinki','(GMT+02:00) Helsinki'),(57,'Europe/Istanbul','(GMT+02:00) Istanbul'),(58,'Asia/Jerusalem','(GMT+02:00) Jerusalem'),(59,'Europe/Kiev','(GMT+02:00) Kyiv'),(60,'Europe/Minsk','(GMT+02:00) Minsk'),(61,'Europe/Riga','(GMT+02:00) Riga'),(62,'Europe/Sofia','(GMT+02:00) Sofia'),(63,'Europe/Tallinn','(GMT+02:00) Tallinn'),(64,'Europe/Vilnius','(GMT+02:00) Vilnius'),(65,'Asia/Baghdad','(GMT+03:00) Baghdad'),(66,'Asia/Kuwait','(GMT+03:00) Kuwait'),(67,'Africa/Nairobi','(GMT+03:00) Nairobi'),(68,'Asia/Riyadh','(GMT+03:00) Riyadh'),(69,'Asia/Tehran','(GMT+03:30) Tehran'),(70,'Europe/Moscow','(GMT+04:00) Moscow'),(71,'Asia/Baku','(GMT+04:00) Baku'),(72,'Europe/Volgograd','(GMT+04:00) Volgograd'),(73,'Asia/Muscat','(GMT+04:00) Muscat'),(74,'Asia/Tbilisi','(GMT+04:00) Tbilisi'),(75,'Asia/Yerevan','(GMT+04:00) Yerevan'),(76,'Asia/Kabul','(GMT+04:30) Kabul'),(77,'Asia/Karachi','(GMT+05:00) Karachi'),(78,'Asia/Tashkent','(GMT+05:00) Tashkent'),(79,'Asia/Kolkata','(GMT+05:30) Kolkata'),(80,'Asia/Kathmandu','(GMT+05:45) Kathmandu'),(81,'Asia/Yekaterinburg','(GMT+06:00) Ekaterinburg'),(82,'Asia/Almaty','(GMT+06:00) Almaty'),(83,'Asia/Dhaka','(GMT+06:00) Dhaka'),(84,'Asia/Novosibirsk','(GMT+07:00) Novosibirsk'),(85,'Asia/Bangkok','(GMT+07:00) Bangkok'),(86,'Asia/Jakarta','(GMT+07:00) Jakarta'),(87,'Asia/Krasnoyarsk','(GMT+08:00) Krasnoyarsk'),(88,'Asia/Chongqing','(GMT+08:00) Chongqing'),(89,'Asia/Hong_Kong','(GMT+08:00) Hong Kong'),(90,'Asia/Kuala_Lumpur','(GMT+08:00) Kuala Lumpur'),(91,'Australia/Perth','(GMT+08:00) Perth'),(92,'Asia/Singapore','(GMT+08:00) Singapore'),(93,'Asia/Taipei','(GMT+08:00) Taipei'),(94,'Asia/Ulaanbaatar','(GMT+08:00) Ulaan Bataar'),(95,'Asia/Urumqi','(GMT+08:00) Urumqi'),(96,'Asia/Irkutsk','(GMT+09:00) Irkutsk'),(97,'Asia/Seoul','(GMT+09:00) Seoul'),(98,'Asia/Tokyo','(GMT+09:00) Tokyo'),(99,'Australia/Adelaide','(GMT+09:30) Adelaide'),(100,'Australia/Darwin','(GMT+09:30) Darwin'),(101,'Asia/Yakutsk','(GMT+10:00) Yakutsk'),(102,'Australia/Brisbane','(GMT+10:00) Brisbane'),(103,'Australia/Canberra','(GMT+10:00) Canberra'),(104,'Pacific/Guam','(GMT+10:00) Guam'),(105,'Australia/Hobart','(GMT+10:00) Hobart'),(106,'Australia/Melbourne','(GMT+10:00) Melbourne'),(107,'Pacific/Port_Moresby','(GMT+10:00) Port Moresby'),(108,'Australia/Sydney','(GMT+10:00) Sydney'),(109,'Asia/Vladivostok','(GMT+11:00) Vladivostok'),(110,'Asia/Magadan','(GMT+12:00) Magadan'),(111,'Pacific/Auckland','(GMT+12:00) Auckland'),(112,'Pacific/Fiji','(GMT+12:00) Fiji');
/*!40000 ALTER TABLE `timezones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `updated_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `first_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `last_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `phone` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `confirmation_code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `registered` tinyint(1) NOT NULL DEFAULT '0',
  `confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `theme_id` int(11) NOT NULL,
  `notify_sent` tinyint(1) NOT NULL DEFAULT '1',
  `notify_viewed` tinyint(1) NOT NULL DEFAULT '0',
  `notify_paid` tinyint(1) NOT NULL DEFAULT '1',
  `public_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_account_id_public_id_unique` (`account_id`,`public_id`),
  UNIQUE KEY `users_username_unique` (`username`),
  KEY `users_account_id_index` (`account_id`),
  CONSTRAINT `users_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
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

-- Dump completed on 2014-02-03 22:56:05
