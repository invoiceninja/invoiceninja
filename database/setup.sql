-- MySQL dump 10.13  Distrib 5.7.18, for Linux (x86_64)
--
-- Host: localhost    Database: ninja
-- ------------------------------------------------------
-- Server version	5.7.18-0ubuntu0.16.04.1

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
-- Table structure for table `account_email_settings`
--

DROP TABLE IF EXISTS `account_email_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_email_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `reply_to_email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `bcc_email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `email_subject_invoice` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email_subject_quote` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email_subject_payment` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email_template_invoice` text COLLATE utf8_unicode_ci NOT NULL,
  `email_template_quote` text COLLATE utf8_unicode_ci NOT NULL,
  `email_template_payment` text COLLATE utf8_unicode_ci NOT NULL,
  `email_subject_reminder1` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email_subject_reminder2` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email_subject_reminder3` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email_template_reminder1` text COLLATE utf8_unicode_ci NOT NULL,
  `email_template_reminder2` text COLLATE utf8_unicode_ci NOT NULL,
  `email_template_reminder3` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `account_email_settings_account_id_index` (`account_id`),
  CONSTRAINT `account_email_settings_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `account_email_settings`
--

LOCK TABLES `account_email_settings` WRITE;
/*!40000 ALTER TABLE `account_email_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `account_email_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `account_gateway_settings`
--

DROP TABLE IF EXISTS `account_gateway_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_gateway_settings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `gateway_type_id` int(10) unsigned DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `min_limit` int(10) unsigned DEFAULT NULL,
  `max_limit` int(10) unsigned DEFAULT NULL,
  `fee_amount` decimal(13,2) DEFAULT NULL,
  `fee_percent` decimal(13,3) DEFAULT NULL,
  `fee_tax_name1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fee_tax_name2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fee_tax_rate1` decimal(13,3) DEFAULT NULL,
  `fee_tax_rate2` decimal(13,3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_gateway_settings_account_id_foreign` (`account_id`),
  KEY `account_gateway_settings_user_id_foreign` (`user_id`),
  KEY `account_gateway_settings_gateway_type_id_foreign` (`gateway_type_id`),
  CONSTRAINT `account_gateway_settings_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_gateway_settings_gateway_type_id_foreign` FOREIGN KEY (`gateway_type_id`) REFERENCES `gateway_types` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_gateway_settings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `account_gateway_settings`
--

LOCK TABLES `account_gateway_settings` WRITE;
/*!40000 ALTER TABLE `account_gateway_settings` DISABLE KEYS */;
/*!40000 ALTER TABLE `account_gateway_settings` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `account_gateway_tokens`
--

DROP TABLE IF EXISTS `account_gateway_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_gateway_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `contact_id` int(10) unsigned NOT NULL,
  `account_gateway_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `token` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `default_payment_method_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `account_gateway_tokens_account_id_foreign` (`account_id`),
  KEY `account_gateway_tokens_contact_id_foreign` (`contact_id`),
  KEY `account_gateway_tokens_account_gateway_id_foreign` (`account_gateway_id`),
  KEY `account_gateway_tokens_client_id_foreign` (`client_id`),
  KEY `account_gateway_tokens_default_payment_method_id_foreign` (`default_payment_method_id`),
  CONSTRAINT `account_gateway_tokens_account_gateway_id_foreign` FOREIGN KEY (`account_gateway_id`) REFERENCES `account_gateways` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_gateway_tokens_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_gateway_tokens_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_gateway_tokens_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_gateway_tokens_default_payment_method_id_foreign` FOREIGN KEY (`default_payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `account_gateway_tokens`
--

LOCK TABLES `account_gateway_tokens` WRITE;
/*!40000 ALTER TABLE `account_gateway_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `account_gateway_tokens` ENABLE KEYS */;
UNLOCK TABLES;

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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `config` text COLLATE utf8_unicode_ci NOT NULL,
  `public_id` int(10) unsigned NOT NULL,
  `accepted_credit_cards` int(10) unsigned DEFAULT NULL,
  `show_address` tinyint(1) DEFAULT '1',
  `update_address` tinyint(1) DEFAULT '1',
  `require_cvv` tinyint(1) DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_gateways_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `account_gateways_gateway_id_foreign` (`gateway_id`),
  KEY `account_gateways_user_id_foreign` (`user_id`),
  KEY `account_gateways_public_id_index` (`public_id`),
  CONSTRAINT `account_gateways_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_gateways_gateway_id_foreign` FOREIGN KEY (`gateway_id`) REFERENCES `gateways` (`id`),
  CONSTRAINT `account_gateways_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
-- Table structure for table `account_tokens`
--

DROP TABLE IF EXISTS `account_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `account_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `token` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `public_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_tokens_token_unique` (`token`),
  UNIQUE KEY `account_tokens_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `account_tokens_user_id_foreign` (`user_id`),
  KEY `account_tokens_account_id_index` (`account_id`),
  CONSTRAINT `account_tokens_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `account_tokens`
--

LOCK TABLES `account_tokens` WRITE;
/*!40000 ALTER TABLE `account_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `account_tokens` ENABLE KEYS */;
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ip` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `account_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `address1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `address2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `postal_code` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `country_id` int(10) unsigned DEFAULT NULL,
  `invoice_terms` text COLLATE utf8_unicode_ci,
  `email_footer` text COLLATE utf8_unicode_ci,
  `industry_id` int(10) unsigned DEFAULT NULL,
  `size_id` int(10) unsigned DEFAULT NULL,
  `invoice_taxes` tinyint(1) NOT NULL DEFAULT '1',
  `invoice_item_taxes` tinyint(1) NOT NULL DEFAULT '0',
  `invoice_design_id` int(10) unsigned NOT NULL DEFAULT '1',
  `work_phone` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `work_email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `language_id` int(10) unsigned NOT NULL DEFAULT '1',
  `custom_label1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `custom_value1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `custom_label2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `custom_value2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `custom_client_label1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `custom_client_label2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `fill_products` tinyint(1) NOT NULL DEFAULT '1',
  `update_products` tinyint(1) NOT NULL DEFAULT '1',
  `primary_color` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `secondary_color` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `hide_quantity` tinyint(1) NOT NULL DEFAULT '0',
  `hide_paid_to_date` tinyint(1) NOT NULL DEFAULT '0',
  `custom_invoice_label1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `custom_invoice_label2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `custom_invoice_taxes1` tinyint(1) DEFAULT NULL,
  `custom_invoice_taxes2` tinyint(1) DEFAULT NULL,
  `vat_number` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `invoice_number_prefix` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `invoice_number_counter` int(11) DEFAULT '1',
  `quote_number_prefix` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `quote_number_counter` int(11) DEFAULT '1',
  `share_counter` tinyint(1) NOT NULL DEFAULT '1',
  `id_number` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `token_billing_type_id` smallint(6) NOT NULL DEFAULT '4',
  `invoice_footer` text COLLATE utf8_unicode_ci,
  `pdf_email_attachment` smallint(6) NOT NULL DEFAULT '0',
  `subdomain` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `font_size` smallint(6) NOT NULL DEFAULT '9',
  `invoice_labels` text COLLATE utf8_unicode_ci,
  `custom_design1` mediumtext COLLATE utf8_unicode_ci,
  `show_item_taxes` tinyint(1) NOT NULL DEFAULT '0',
  `iframe_url` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `military_time` tinyint(1) NOT NULL DEFAULT '0',
  `enable_reminder1` tinyint(1) NOT NULL DEFAULT '0',
  `enable_reminder2` tinyint(1) NOT NULL DEFAULT '0',
  `enable_reminder3` tinyint(1) NOT NULL DEFAULT '0',
  `num_days_reminder1` smallint(6) NOT NULL DEFAULT '7',
  `num_days_reminder2` smallint(6) NOT NULL DEFAULT '14',
  `num_days_reminder3` smallint(6) NOT NULL DEFAULT '30',
  `custom_invoice_text_label1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `custom_invoice_text_label2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `recurring_hour` smallint(6) NOT NULL DEFAULT '8',
  `invoice_number_pattern` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `quote_number_pattern` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `quote_terms` text COLLATE utf8_unicode_ci,
  `email_design_id` smallint(6) NOT NULL DEFAULT '1',
  `enable_email_markup` tinyint(1) NOT NULL DEFAULT '0',
  `website` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `direction_reminder1` smallint(6) NOT NULL DEFAULT '1',
  `direction_reminder2` smallint(6) NOT NULL DEFAULT '1',
  `direction_reminder3` smallint(6) NOT NULL DEFAULT '1',
  `field_reminder1` smallint(6) NOT NULL DEFAULT '1',
  `field_reminder2` smallint(6) NOT NULL DEFAULT '1',
  `field_reminder3` smallint(6) NOT NULL DEFAULT '1',
  `client_view_css` text COLLATE utf8_unicode_ci,
  `header_font_id` int(10) unsigned NOT NULL DEFAULT '1',
  `body_font_id` int(10) unsigned NOT NULL DEFAULT '1',
  `auto_convert_quote` tinyint(1) NOT NULL DEFAULT '1',
  `all_pages_footer` tinyint(1) NOT NULL,
  `all_pages_header` tinyint(1) NOT NULL,
  `show_currency_code` tinyint(1) NOT NULL,
  `enable_portal_password` tinyint(1) NOT NULL DEFAULT '0',
  `send_portal_password` tinyint(1) NOT NULL DEFAULT '0',
  `custom_invoice_item_label1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `custom_invoice_item_label2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `recurring_invoice_number_prefix` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'R',
  `enable_client_portal` tinyint(1) NOT NULL DEFAULT '1',
  `invoice_fields` text COLLATE utf8_unicode_ci,
  `devices` text COLLATE utf8_unicode_ci,
  `logo` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `logo_width` int(10) unsigned NOT NULL,
  `logo_height` int(10) unsigned NOT NULL,
  `logo_size` int(10) unsigned NOT NULL,
  `invoice_embed_documents` tinyint(1) NOT NULL DEFAULT '0',
  `document_email_attachment` tinyint(1) NOT NULL DEFAULT '0',
  `enable_client_portal_dashboard` tinyint(1) NOT NULL DEFAULT '1',
  `company_id` int(10) unsigned DEFAULT NULL,
  `page_size` varchar(255) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'A4',
  `live_preview` tinyint(1) NOT NULL DEFAULT '1',
  `invoice_number_padding` smallint(6) NOT NULL DEFAULT '4',
  `enable_second_tax_rate` tinyint(1) NOT NULL DEFAULT '0',
  `auto_bill_on_due_date` tinyint(1) NOT NULL DEFAULT '0',
  `start_of_week` int(11) NOT NULL,
  `enable_buy_now_buttons` tinyint(1) NOT NULL DEFAULT '0',
  `include_item_taxes_inline` tinyint(1) NOT NULL DEFAULT '0',
  `financial_year_start` date DEFAULT NULL,
  `enabled_modules` smallint(6) NOT NULL DEFAULT '63',
  `enabled_dashboard_sections` smallint(6) NOT NULL DEFAULT '7',
  `show_accept_invoice_terms` tinyint(1) NOT NULL DEFAULT '0',
  `show_accept_quote_terms` tinyint(1) NOT NULL DEFAULT '0',
  `require_invoice_signature` tinyint(1) NOT NULL DEFAULT '0',
  `require_quote_signature` tinyint(1) NOT NULL DEFAULT '0',
  `client_number_prefix` text COLLATE utf8_unicode_ci,
  `client_number_counter` int(11) DEFAULT '0',
  `client_number_pattern` text COLLATE utf8_unicode_ci,
  `domain_id` tinyint(3) unsigned DEFAULT '1',
  `payment_terms` tinyint(4) DEFAULT NULL,
  `reset_counter_frequency_id` smallint(6) DEFAULT NULL,
  `payment_type_id` smallint(6) DEFAULT NULL,
  `gateway_fee_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `reset_counter_date` date DEFAULT NULL,
  `custom_contact_label1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `custom_contact_label2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tax_name1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tax_rate1` decimal(13,3) NOT NULL,
  `tax_name2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tax_rate2` decimal(13,3) NOT NULL,
  `quote_design_id` int(10) unsigned NOT NULL DEFAULT '1',
  `custom_design2` mediumtext COLLATE utf8_unicode_ci,
  `custom_design3` mediumtext COLLATE utf8_unicode_ci,
  `analytics_key` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `accounts_account_key_unique` (`account_key`),
  KEY `accounts_timezone_id_foreign` (`timezone_id`),
  KEY `accounts_date_format_id_foreign` (`date_format_id`),
  KEY `accounts_datetime_format_id_foreign` (`datetime_format_id`),
  KEY `accounts_country_id_foreign` (`country_id`),
  KEY `accounts_currency_id_foreign` (`currency_id`),
  KEY `accounts_industry_id_foreign` (`industry_id`),
  KEY `accounts_size_id_foreign` (`size_id`),
  KEY `accounts_invoice_design_id_foreign` (`invoice_design_id`),
  KEY `accounts_language_id_foreign` (`language_id`),
  KEY `accounts_company_id_foreign` (`company_id`),
  CONSTRAINT `accounts_company_id_foreign` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `accounts_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `accounts_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `accounts_date_format_id_foreign` FOREIGN KEY (`date_format_id`) REFERENCES `date_formats` (`id`),
  CONSTRAINT `accounts_datetime_format_id_foreign` FOREIGN KEY (`datetime_format_id`) REFERENCES `datetime_formats` (`id`),
  CONSTRAINT `accounts_industry_id_foreign` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`),
  CONSTRAINT `accounts_invoice_design_id_foreign` FOREIGN KEY (`invoice_design_id`) REFERENCES `invoice_designs` (`id`),
  CONSTRAINT `accounts_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`),
  CONSTRAINT `accounts_size_id_foreign` FOREIGN KEY (`size_id`) REFERENCES `sizes` (`id`),
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `contact_id` int(10) unsigned DEFAULT NULL,
  `payment_id` int(10) unsigned DEFAULT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `credit_id` int(10) unsigned DEFAULT NULL,
  `invitation_id` int(10) unsigned DEFAULT NULL,
  `task_id` int(11) DEFAULT NULL,
  `json_backup` text COLLATE utf8_unicode_ci,
  `activity_type_id` int(11) NOT NULL,
  `adjustment` decimal(13,2) DEFAULT NULL,
  `balance` decimal(13,2) DEFAULT NULL,
  `token_id` int(10) unsigned DEFAULT NULL,
  `ip` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `expense_id` int(10) unsigned DEFAULT NULL,
  `notes` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `activities_account_id_foreign` (`account_id`),
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
-- Table structure for table `affiliates`
--

DROP TABLE IF EXISTS `affiliates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `affiliates` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `affiliate_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `payment_title` text COLLATE utf8_unicode_ci NOT NULL,
  `payment_subtitle` text COLLATE utf8_unicode_ci NOT NULL,
  `price` decimal(7,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `affiliates_affiliate_key_unique` (`affiliate_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `affiliates`
--

LOCK TABLES `affiliates` WRITE;
/*!40000 ALTER TABLE `affiliates` DISABLE KEYS */;
/*!40000 ALTER TABLE `affiliates` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_accounts`
--

DROP TABLE IF EXISTS `bank_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `bank_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `public_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_accounts_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `bank_accounts_user_id_foreign` (`user_id`),
  KEY `bank_accounts_bank_id_foreign` (`bank_id`),
  KEY `bank_accounts_public_id_index` (`public_id`),
  CONSTRAINT `bank_accounts_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_accounts_bank_id_foreign` FOREIGN KEY (`bank_id`) REFERENCES `banks` (`id`),
  CONSTRAINT `bank_accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_accounts`
--

LOCK TABLES `bank_accounts` WRITE;
/*!40000 ALTER TABLE `bank_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `bank_subaccounts`
--

DROP TABLE IF EXISTS `bank_subaccounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `bank_subaccounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `bank_account_id` int(10) unsigned NOT NULL,
  `account_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `account_number` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `public_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bank_subaccounts_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `bank_subaccounts_user_id_foreign` (`user_id`),
  KEY `bank_subaccounts_bank_account_id_foreign` (`bank_account_id`),
  KEY `bank_subaccounts_public_id_index` (`public_id`),
  CONSTRAINT `bank_subaccounts_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_subaccounts_bank_account_id_foreign` FOREIGN KEY (`bank_account_id`) REFERENCES `bank_accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `bank_subaccounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `bank_subaccounts`
--

LOCK TABLES `bank_subaccounts` WRITE;
/*!40000 ALTER TABLE `bank_subaccounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `bank_subaccounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `banks`
--

DROP TABLE IF EXISTS `banks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `banks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `remote_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `bank_library_id` int(11) NOT NULL DEFAULT '1',
  `config` text COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=387 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `banks`
--

LOCK TABLES `banks` WRITE;
/*!40000 ALTER TABLE `banks` DISABLE KEYS */;
INSERT INTO `banks` VALUES (1,'ING DIRECT (Canada)','421',1,'{\"fid\":\"061400152\",\"org\":\"INGDirectCanada\",\"url\":\"https:\\/\\/ofx.ingdirect.ca\"}'),(2,'Safe Credit Union - OFX Beta','422',1,'{\"fid\":\"321173742\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxcert.diginsite.com\\/cmr\\/cmr.ofx\"}'),(3,'Ascentra Credit Union','423',1,'{\"fid\":\"273973456\",\"org\":\"Alcoa Employees&Community CU\",\"url\":\"https:\\/\\/alc.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(4,'American Express Card','424',1,'{\"fid\":\"3101\",\"org\":\"AMEX\",\"url\":\"https:\\/\\/online.americanexpress.com\\/myca\\/ofxdl\\/desktop\\/desktopDownload.do?request_type=nl_ofxdownload\"}'),(5,'TD Ameritrade','425',1,'{\"fid\":\"5024\",\"org\":\"ameritrade.com\",\"url\":\"https:\\/\\/ofxs.ameritrade.com\\/cgi-bin\\/apps\\/OFX\"}'),(6,'Truliant FCU','426',1,'{\"fid\":\"253177832\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(7,'AT&T Universal Card','427',1,'{\"fid\":\"24909\",\"org\":\"Citigroup\",\"url\":\"https:\\/\\/secureofx2.bankhost.com\\/citi\\/cgi-forte\\/ofx_rt?servicename=ofx_rt&pagename=ofx\"}'),(8,'Bank One','428',1,'{\"fid\":\"5811\",\"org\":\"B1\",\"url\":\"https:\\/\\/onlineofx.chase.com\\/chase.ofx\"}'),(9,'Bank of Stockton','429',1,'{\"fid\":\"3901\",\"org\":\"BOS\",\"url\":\"https:\\/\\/internetbanking.bankofstockton.com\\/scripts\\/serverext.dll\"}'),(10,'Bank of the Cascades','430',1,'{\"fid\":\"4751\",\"org\":\"JackHenry\",\"url\":\"https:\\/\\/directline.netteller.com\"}'),(11,'Centra Credit Union','431',1,'{\"fid\":\"274972883\",\"org\":\"Centra CU\",\"url\":\"https:\\/\\/centralink.org\\/scripts\\/isaofx.dll\"}'),(12,'Centura Bank','432',1,'{\"fid\":\"1901\",\"org\":\"Centura Bank\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/1901.ofxgp\"}'),(13,'Charles Schwab&Co., INC','433',1,'{\"fid\":\"5104\",\"org\":\"ISC\",\"url\":\"https:\\/\\/ofx.schwab.com\\/cgi_dev\\/ofx_server\"}'),(14,'JPMorgan Chase Bank (Texas)','434',1,'{\"fid\":\"5301\",\"org\":\"Chase Bank of Texas\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/5301.ofxgp\"}'),(15,'JPMorgan Chase Bank','435',1,'{\"fid\":\"1601\",\"org\":\"Chase Bank\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/1601.ofxgp\"}'),(16,'Colonial Bank','436',1,'{\"fid\":\"1046\",\"org\":\"Colonial Banc Group\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/1046.ofxgp\"}'),(17,'Comerica Bank','437',1,'{\"fid\":\"5601\",\"org\":\"Comerica\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/5601.ofxgp\"}'),(18,'Commerce Bank NJ, PA, NY&DE','438',1,'{\"fid\":\"1001\",\"org\":\"CommerceBank\",\"url\":\"https:\\/\\/www.commerceonlinebanking.com\\/scripts\\/serverext.dll\"}'),(19,'Commerce Bank, NA','439',1,'{\"fid\":\"4001\",\"org\":\"Commerce Bank NA\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/4001.ofxgp\"}'),(20,'Commercial Federal Bank','440',1,'{\"fid\":\"4801\",\"org\":\"CommercialFederalBank\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/4801.ofxgp\"}'),(21,'COMSTAR FCU','441',1,'{\"fid\":\"255074988\",\"org\":\"Comstar Federal Credit Union\",\"url\":\"https:\\/\\/pcu.comstarfcu.org\\/scripts\\/isaofx.dll\"}'),(22,'SunTrust','442',1,'{\"fid\":\"2801\",\"org\":\"SunTrust PC Banking\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/2801.ofxgp\"}'),(23,'Denali Alaskan FCU','443',1,'{\"fid\":\"1\",\"org\":\"Denali Alaskan FCU\",\"url\":\"https:\\/\\/remotebanking.denalifcu.com\\/ofx\\/ofx.dll\"}'),(24,'Discover Card','444',1,'{\"fid\":\"7101\",\"org\":\"Discover Financial Services\",\"url\":\"https:\\/\\/ofx.discovercard.com\"}'),(25,'E*TRADE','446',1,'{\"fid\":\"fldProv_mProvBankId\",\"org\":\"fldProv_mId\",\"url\":\"https:\\/\\/ofx.etrade.com\\/cgi-ofx\\/etradeofx\"}'),(26,'Eastern Bank','447',1,'{\"fid\":\"6201\",\"org\":\"Eastern Bank\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/6201.ofxgp\"}'),(27,'EDS Credit Union','448',1,'{\"fid\":\"311079474\",\"org\":\"EDS CU\",\"url\":\"https:\\/\\/eds.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(28,'Fidelity Investments','449',1,'{\"fid\":\"7776\",\"org\":\"fidelity.com\",\"url\":\"https:\\/\\/ofx.fidelity.com\\/ftgw\\/OFX\\/clients\\/download\"}'),(29,'Fifth Third Bancorp','450',1,'{\"fid\":\"5829\",\"org\":\"Fifth Third Bank\",\"url\":\"https:\\/\\/banking.53.com\\/ofx\\/OFXServlet\"}'),(30,'First Tech Credit Union','451',1,'{\"fid\":\"2243\",\"org\":\"First Tech Credit Union\",\"url\":\"https:\\/\\/ofx.firsttechcu.com\"}'),(31,'zWachovia','452',1,'{\"fid\":\"4301\",\"org\":\"Wachovia\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/4301.ofxgp\"}'),(32,'KeyBank','453',1,'{\"fid\":\"5901\",\"org\":\"KeyBank\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/05901.ofx\"}'),(33,'Mellon Bank','454',1,'{\"fid\":\"1226\",\"org\":\"Mellon Bank\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/1226.ofxgp\"}'),(34,'LaSalle Bank Midwest','455',1,'{\"fid\":\"1101\",\"org\":\"LaSalleBankMidwest\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/1101.ofxgp\"}'),(35,'Nantucket Bank','456',1,'{\"fid\":\"466\",\"org\":\"Nantucket\",\"url\":\"https:\\/\\/ofx.onlinencr.com\\/scripts\\/serverext.dll\"}'),(36,'National Penn Bank','457',1,'{\"fid\":\"6301\",\"org\":\"National Penn Bank\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/6301.ofxgp\"}'),(37,'Nevada State Bank - New','458',1,'{\"fid\":\"1121\",\"org\":\"295-3\",\"url\":\"https:\\/\\/quicken.metavante.com\\/ofx\\/OFXServlet\"}'),(38,'UBS Financial Services Inc.','459',1,'{\"fid\":\"7772\",\"org\":\"Intuit\",\"url\":\"https:\\/\\/ofx1.ubs.com\\/eftxweb\\/access.ofx\"}'),(39,'Patelco CU','460',1,'{\"fid\":\"2000\",\"org\":\"Patelco Credit Union\",\"url\":\"https:\\/\\/ofx.patelco.org\"}'),(40,'Mercantile Brokerage Services','461',1,'{\"fid\":\"011\",\"org\":\"Mercantile Brokerage\",\"url\":\"https:\\/\\/ofx.netxclient.com\\/cgi\\/OFXNetx\"}'),(41,'Regions Bank','462',1,'{\"fid\":\"243\",\"org\":\"regions.com\",\"url\":\"https:\\/\\/ofx.morgankeegan.com\\/begasp\\/directtocore.asp\"}'),(42,'Spectrum Connect/Reich&Tang','463',1,'{\"fid\":\"6510\",\"org\":\"SpectrumConnect\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/6510.ofxgp\"}'),(43,'Smith Barney - Transactions','464',1,'{\"fid\":\"3201\",\"org\":\"SmithBarney\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/3201.ofxgp\"}'),(44,'Southwest Airlines FCU','465',1,'{\"fid\":\"311090673\",\"org\":\"Southwest Airlines EFCU\",\"url\":\"https:\\/\\/www.swacuflashbp.org\\/scripts\\/isaofx.dll\"}'),(45,'Technology Credit Union - CA','467',1,'{\"fid\":\"11257\",\"org\":\"Tech CU\",\"url\":\"https:\\/\\/webbranchofx.techcu.com\\/TekPortalOFX\\/servlet\\/TP_OFX_Controller\"}'),(46,'UMB Bank','468',1,'{\"fid\":\"0\",\"org\":\"UMB\",\"url\":\"https:\\/\\/pcbanking.umb.com\\/hs_ofx\\/hsofx.dll\"}'),(47,'Union Bank of California','469',1,'{\"fid\":\"2901\",\"org\":\"Union Bank of California\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/2901.ofxgp\"}'),(48,'United Teletech Financial','470',1,'{\"fid\":\"221276011\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxcore.digitalinsight.com:443\\/servlet\\/OFXCoreServlet\"}'),(49,'US Bank','471',1,'{\"fid\":\"1401\",\"org\":\"US Bank\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/1401.ofxgp\"}'),(50,'Bank of America (All except CA, WA,&ID)','472',1,'{\"fid\":\"6812\",\"org\":\"HAN\",\"url\":\"https:\\/\\/ofx.bankofamerica.com\\/cgi-forte\\/fortecgi?servicename=ofx_2-3&pagename=ofx\"}'),(51,'Wells Fargo','473',1,'{\"fid\":\"3000\",\"org\":\"WF\",\"url\":\"https:\\/\\/ofxdc.wellsfargo.com\\/ofx\\/process.ofx\"}'),(52,'LaSalle Bank NA','474',1,'{\"fid\":\"6501\",\"org\":\"LaSalle Bank NA\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/6501.ofxgp\"}'),(53,'BB&T','475',1,'{\"fid\":\"BB&T\",\"org\":\"BB&T\",\"url\":\"https:\\/\\/eftx.bbt.com\\/eftxweb\\/access.ofx\"}'),(54,'Los Alamos National Bank','476',1,'{\"fid\":\"107001012\",\"org\":\"LANB\",\"url\":\"https:\\/\\/ofx.lanb.com\\/ofx\\/ofxrelay.dll\"}'),(55,'Citadel FCU','477',1,'{\"fid\":\"citadel\",\"org\":\"CitadelFCU\",\"url\":\"https:\\/\\/pcu.citadelfcu.org\\/scripts\\/isaofx.dll\"}'),(56,'Clearview Federal Credit Union','478',1,'{\"fid\":\"243083237\",\"org\":\"Clearview Federal Credit Union\",\"url\":\"https:\\/\\/www.pcu.clearviewfcu.org\\/scripts\\/isaofx.dll\"}'),(57,'Vanguard Group, The','479',1,'{\"fid\":\"1358\",\"org\":\"The Vanguard Group\",\"url\":\"https:\\/\\/vesnc.vanguard.com\\/us\\/OfxDirectConnectServlet\"}'),(58,'First Citizens Bank - NC, VA, WV','480',1,'{\"fid\":\"5013\",\"org\":\"First Citizens Bank NC, VA, WV\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/5013.ofxgp\"}'),(59,'Northern Trust - Banking','481',1,'{\"fid\":\"5804\",\"org\":\"ORG\",\"url\":\"https:\\/\\/www3883.ntrs.com\\/nta\\/ofxservlet\"}'),(60,'The Mechanics Bank','482',1,'{\"fid\":\"121102036\",\"org\":\"TMB\",\"url\":\"https:\\/\\/ofx.mechbank.com\\/OFXServer\\/ofxsrvr.dll\"}'),(61,'USAA Federal Savings Bank','483',1,'{\"fid\":\"24591\",\"org\":\"USAA\",\"url\":\"https:\\/\\/service2.usaa.com\\/ofx\\/OFXServlet\"}'),(62,'Florida Telco CU','484',1,'{\"fid\":\"FTCU\",\"org\":\"FloridaTelcoCU\",\"url\":\"https:\\/\\/ppc.floridatelco.org\\/scripts\\/isaofx.dll\"}'),(63,'DuPont Community Credit Union','485',1,'{\"fid\":\"251483311\",\"org\":\"DuPont Community Credit Union\",\"url\":\"https:\\/\\/pcu.mydccu.com\\/scripts\\/isaofx.dll\"}'),(64,'Central Florida Educators FCU','486',1,'{\"fid\":\"590678236\",\"org\":\"CentralFloridaEduc\",\"url\":\"https:\\/\\/www.mattweb.cfefcu.com\\/scripts\\/isaofx.dll\"}'),(65,'California Bank&Trust','487',1,'{\"fid\":\"5006\",\"org\":\"401\",\"url\":\"https:\\/\\/pfm.metavante.com\\/ofx\\/OFXServlet\"}'),(66,'First Commonwealth FCU','488',1,'{\"fid\":\"231379199\",\"org\":\"FirstCommonwealthFCU\",\"url\":\"https:\\/\\/pcu.firstcomcu.org\\/scripts\\/isaofx.dll\"}'),(67,'Ameriprise Financial Services, Inc.','489',1,'{\"fid\":\"3102\",\"org\":\"AMPF\",\"url\":\"https:\\/\\/www25.ameriprise.com\\/AMPFWeb\\/ofxdl\\/us\\/download?request_type=nl_desktopdownload\"}'),(68,'AltaOne Federal Credit Union','490',1,'{\"fid\":\"322274462\",\"org\":\"AltaOneFCU\",\"url\":\"https:\\/\\/pcu.altaone.org\\/scripts\\/isaofx.dll\"}'),(69,'A. G. Edwards and Sons, Inc.','491',1,'{\"fid\":\"43-0895447\",\"org\":\"A.G. Edwards\",\"url\":\"https:\\/\\/ofx.agedwards.com\"}'),(70,'Educational Employees CU Fresno','492',1,'{\"fid\":\"321172594\",\"org\":\"Educational Employees C U\",\"url\":\"https:\\/\\/www.eecuonline.org\\/scripts\\/isaofx.dll\"}'),(71,'Hawthorne Credit Union','493',1,'{\"fid\":\"271979193\",\"org\":\"Hawthorne Credit Union\",\"url\":\"https:\\/\\/hwt.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(72,'Firstar','494',1,'{\"fid\":\"1255\",\"org\":\"Firstar\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/1255.ofxgp\"}'),(73,'myStreetscape','495',1,'{\"fid\":\"7784\",\"org\":\"Fidelity\",\"url\":\"https:\\/\\/ofx.ibgstreetscape.com:443\"}'),(74,'Collegedale Credit Union','496',1,'{\"fid\":\"35GFA\",\"org\":\"CollegedaleCU\",\"url\":\"https:\\/\\/www.netit.financial-net.com\\/ofx\"}'),(75,'GCS Federal Credit Union','498',1,'{\"fid\":\"281076853\",\"org\":\"Granite City Steel cu\",\"url\":\"https:\\/\\/pcu.mygcscu.com\\/scripts\\/isaofx.dll\"}'),(76,'Vantage Credit Union','499',1,'{\"fid\":\"281081479\",\"org\":\"EECU-St. Louis\",\"url\":\"https:\\/\\/secure2.eecu.com\\/scripts\\/isaofx.dll\"}'),(77,'Morgan Stanley ClientServ','500',1,'{\"fid\":\"1235\",\"org\":\"msdw.com\",\"url\":\"https:\\/\\/ofx.morganstanleyclientserv.com\\/ofx\\/ProfileMSMoney.ofx\"}'),(78,'Kennedy Space Center FCU','501',1,'{\"fid\":\"263179532\",\"org\":\"Kennedy Space Center FCU\",\"url\":\"https:\\/\\/www.pcu.kscfcu.org\\/scripts\\/isaofx.dll\"}'),(79,'Sierra Central Credit Union','502',1,'{\"fid\":\"321174770\",\"org\":\"Sierra Central Credit Union\",\"url\":\"https:\\/\\/www.sierracpu.com\\/scripts\\/isaofx.dll\"}'),(80,'Virginia Educators Credit Union','503',1,'{\"fid\":\"251481355\",\"org\":\"Virginia Educators CU\",\"url\":\"https:\\/\\/www.vecumoneylink.org\\/scripts\\/isaofx.dll\"}'),(81,'Red Crown Federal Credit Union','504',1,'{\"fid\":\"303986148\",\"org\":\"Red Crown FCU\",\"url\":\"https:\\/\\/cre.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(82,'B-M S Federal Credit Union','505',1,'{\"fid\":\"221277007\",\"org\":\"B-M S Federal Credit Union\",\"url\":\"https:\\/\\/bms.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(83,'Fort Stewart GeorgiaFCU','506',1,'{\"fid\":\"261271364\",\"org\":\"Fort Stewart FCU\",\"url\":\"https:\\/\\/fsg.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(84,'Northern Trust - Investments','507',1,'{\"fid\":\"6028\",\"org\":\"Northern Trust Investments\",\"url\":\"https:\\/\\/www3883.ntrs.com\\/nta\\/ofxservlet?accounttypegroup=INV\"}'),(85,'Picatinny Federal Credit Union','508',1,'{\"fid\":\"221275216\",\"org\":\"Picatinny Federal Credit Union\",\"url\":\"https:\\/\\/banking.picacreditunion.com\\/scripts\\/isaofx.dll\"}'),(86,'SAC FEDERAL CREDIT UNION','509',1,'{\"fid\":\"091901480\",\"org\":\"SAC Federal CU\",\"url\":\"https:\\/\\/pcu.sacfcu.com\\/scripts\\/isaofx.dll\"}'),(87,'Merrill Lynch&Co., Inc.','510',1,'{\"fid\":\"5550\",\"org\":\"Merrill Lynch & Co., Inc.\",\"url\":\"https:\\/\\/taxcert.mlol.ml.com\\/eftxweb\\/access.ofx\"}'),(88,'Southeastern CU','511',1,'{\"fid\":\"261271500\",\"org\":\"Southeastern FCU\",\"url\":\"https:\\/\\/moo.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(89,'Texas Dow Employees Credit Union','512',1,'{\"fid\":\"313185515\",\"org\":\"TexasDow\",\"url\":\"https:\\/\\/allthetime.tdecu.org\\/scripts\\/isaofx.dll\"}'),(90,'University Federal Credit Union','513',1,'{\"fid\":\"314977405\",\"org\":\"Univerisity FCU\",\"url\":\"https:\\/\\/OnDemand.ufcu.org\\/scripts\\/isaofx.dll\"}'),(91,'Yakima Valley Credit Union','514',1,'{\"fid\":\"325183796\",\"org\":\"Yakima Valley Credit Union\",\"url\":\"https:\\/\\/secure1.yvcu.org\\/scripts\\/isaofx.dll\"}'),(92,'First Community FCU','515',1,'{\"fid\":\"272483633\",\"org\":\"FirstCommunityFCU\",\"url\":\"https:\\/\\/pcu.1stcomm.org\\/scripts\\/isaofx.dll\"}'),(93,'Wells Fargo Advisor','516',1,'{\"fid\":\"1030\",\"org\":\"strong.com\",\"url\":\"https:\\/\\/ofx.wellsfargoadvantagefunds.com\\/eftxWeb\\/Access.ofx\"}'),(94,'Chicago Patrolmens FCU','517',1,'{\"fid\":\"271078146\",\"org\":\"Chicago Patrolmens CU\",\"url\":\"https:\\/\\/chp.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(95,'Signal Financial Federal Credit Union','518',1,'{\"fid\":\"255075495\",\"org\":\"Washington Telephone FCU\",\"url\":\"https:\\/\\/webpb.sfonline.org\\/scripts\\/isaofx.dll\"}'),(96,'Bank-Fund Staff FCU','520',1,'{\"fid\":\"2\",\"org\":\"Bank Fund Staff FCU\",\"url\":\"https:\\/\\/secure.bfsfcu.org\\/ofx\\/ofx.dll\"}'),(97,'APCO EMPLOYEES CREDIT UNION','521',1,'{\"fid\":\"262087609\",\"org\":\"APCO Employees Credit Union\",\"url\":\"https:\\/\\/apc.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(98,'Bank of Tampa, The','522',1,'{\"fid\":\"063108680\",\"org\":\"BOT\",\"url\":\"https:\\/\\/OFX.Bankoftampa.com\\/OFXServer\\/ofxsrvr.dll\"}'),(99,'Cedar Point Federal Credit Union','523',1,'{\"fid\":\"255077736\",\"org\":\"Cedar Point Federal Credit Union\",\"url\":\"https:\\/\\/pcu.cpfcu.com\\/scripts\\/isaofx.dll\"}'),(100,'Las Colinas FCU','524',1,'{\"fid\":\"311080573\",\"org\":\"Las Colinas Federal CU\",\"url\":\"https:\\/\\/las.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(101,'McCoy Federal Credit Union','525',1,'{\"fid\":\"263179956\",\"org\":\"McCoy Federal Credit Union\",\"url\":\"https:\\/\\/www.mccoydirect.org\\/scripts\\/isaofx.dll\"}'),(102,'Old National Bank','526',1,'{\"fid\":\"11638\",\"org\":\"ONB\",\"url\":\"https:\\/\\/www.ofx.oldnational.com\\/ofxpreprocess.asp\"}'),(103,'Citizens Bank - Consumer','527',1,'{\"fid\":\"CTZBK\",\"org\":\"CheckFree OFX\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/0CTZBK.ofxgp\"}'),(104,'Citizens Bank - Business','528',1,'{\"fid\":\"4639\",\"org\":\"CheckFree OFX\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/04639.ofxgp\"}'),(105,'Century Federal Credit Union','529',1,'{\"fid\":\"241075056\",\"org\":\"CenturyFederalCU\",\"url\":\"https:\\/\\/pcu.cenfedcu.org\\/scripts\\/isaofx.dll\"}'),(106,'ABNB Federal Credit Union','530',1,'{\"fid\":\"251481627\",\"org\":\"ABNB Federal Credit Union\",\"url\":\"https:\\/\\/cuathome.abnbfcu.org\\/scripts\\/isaofx.dll\"}'),(107,'Allegiance Credit Union','531',1,'{\"fid\":\"303085230\",\"org\":\"Federal Employees CU\",\"url\":\"https:\\/\\/fed.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(108,'Wright Patman Congressional FCU','532',1,'{\"fid\":\"254074345\",\"org\":\"Wright Patman Congressional FCU\",\"url\":\"https:\\/\\/www.congressionalonline.org\\/scripts\\/isaofx.dll\"}'),(109,'America First Credit Union','533',1,'{\"fid\":\"54324\",\"org\":\"America First Credit Union\",\"url\":\"https:\\/\\/ofx.americafirst.com\"}'),(110,'Motorola Employees Credit Union','534',1,'{\"fid\":\"271984311\",\"org\":\"Motorola Employees CU\",\"url\":\"https:\\/\\/mecuofx.mecunet.org\\/scripts\\/isaofx.dll\"}'),(111,'Finance Center FCU (IN)','535',1,'{\"fid\":\"274073876\",\"org\":\"Finance Center FCU\",\"url\":\"https:\\/\\/sec.fcfcu.com\\/scripts\\/isaofx.dll\"}'),(112,'Fort Knox Federal Credit Union','536',1,'{\"fid\":\"283978425\",\"org\":\"Fort Knox Federal Credit Union\",\"url\":\"https:\\/\\/fcs1.fkfcu.org\\/scripts\\/isaofx.dll\"}'),(113,'Wachovia Bank','537',1,'{\"fid\":\"4309\",\"org\":\"Wachovia\",\"url\":\"https:\\/\\/pfmpw.wachovia.com\\/cgi-forte\\/fortecgi?servicename=ofx&pagename=PFM\"}'),(114,'Think Federal Credit Union','538',1,'{\"fid\":\"291975465\",\"org\":\"IBMCU\",\"url\":\"https:\\/\\/ofx.ibmcu.com\"}'),(115,'PSECU','539',1,'{\"fid\":\"54354\",\"org\":\"Teknowledge\",\"url\":\"https:\\/\\/ofx.psecu.com\\/servlet\\/OFXServlet\"}'),(116,'Envision Credit Union','540',1,'{\"fid\":\"263182558\",\"org\":\"Envision Credit Union\",\"url\":\"https:\\/\\/pcu.envisioncu.com\\/scripts\\/isaofx.dll\"}'),(117,'Columbia Credit Union','541',1,'{\"fid\":\"323383349\",\"org\":\"Columbia Credit Union\",\"url\":\"https:\\/\\/ofx.columbiacu.org\\/scripts\\/isaofx.dll\"}'),(118,'1st Advantage FCU','542',1,'{\"fid\":\"251480563\",\"org\":\"1st Advantage FCU\",\"url\":\"https:\\/\\/members.1stadvantage.org\\/scripts\\/isaofx.dll\"}'),(119,'Central Maine FCU','543',1,'{\"fid\":\"211287926\",\"org\":\"Central Maine FCU\",\"url\":\"https:\\/\\/cro.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(120,'Kirtland Federal Credit Union','544',1,'{\"fid\":\"307070050\",\"org\":\"Kirtland Federal Credit Union\",\"url\":\"https:\\/\\/pcu.kirtlandfcu.org\\/scripts\\/isaofx.dll\"}'),(121,'Chesterfield Federal Credit Union','545',1,'{\"fid\":\"251480327\",\"org\":\"Chesterfield Employees FCU\",\"url\":\"https:\\/\\/chf.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(122,'Campus USA Credit Union','546',1,'{\"fid\":\"263178478\",\"org\":\"Campus USA Credit Union\",\"url\":\"https:\\/\\/que.campuscu.com\\/scripts\\/isaofx.dll\"}'),(123,'Summit Credit Union (WI)','547',1,'{\"fid\":\"275979034\",\"org\":\"Summit Credit Union\",\"url\":\"https:\\/\\/branch.summitcreditunion.com\\/scripts\\/isaofx.dll\"}'),(124,'Financial Center CU','548',1,'{\"fid\":\"321177803\",\"org\":\"Fincancial Center Credit Union\",\"url\":\"https:\\/\\/fin.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(125,'Hawaiian Tel Federal Credit Union','549',1,'{\"fid\":\"321379070\",\"org\":\"Hawaiian Tel FCU\",\"url\":\"https:\\/\\/htl.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(126,'Addison Avenue Federal Credit Union','550',1,'{\"fid\":\"11288\",\"org\":\"hpcu\",\"url\":\"https:\\/\\/ofx.addisonavenue.com\"}'),(127,'Navy Army Federal Credit Union','551',1,'{\"fid\":\"111904503\",\"org\":\"Navy Army Federal Credit Union\",\"url\":\"https:\\/\\/mybranch.navyarmyfcu.com\\/scripts\\/isaofx.dll\"}'),(128,'Nevada Federal Credit Union','552',1,'{\"fid\":\"10888\",\"org\":\"PSI\",\"url\":\"https:\\/\\/ssl4.nevadafederal.org\\/ofxdirect\\/ofxrqst.aspx\"}'),(129,'66 Federal Credit Union','553',1,'{\"fid\":\"289\",\"org\":\"SixySix\",\"url\":\"https:\\/\\/ofx.cuonlineaccounts.org\"}'),(130,'FirstBank of Colorado','554',1,'{\"fid\":\"FirstBank\",\"org\":\"FBDC\",\"url\":\"https:\\/\\/www.efirstbankpfm.com\\/ofx\\/OFXServlet\"}'),(131,'Continental Federal Credit Union','555',1,'{\"fid\":\"322077559\",\"org\":\"Continenetal FCU\",\"url\":\"https:\\/\\/cnt.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(132,'Fremont Bank','556',1,'{\"fid\":\"121107882\",\"org\":\"Fremont Bank\",\"url\":\"https:\\/\\/ofx.fremontbank.com\\/OFXServer\\/FBOFXSrvr.dll\"}'),(133,'Peninsula Community Federal Credit Union','557',1,'{\"fid\":\"325182344\",\"org\":\"Peninsula Credit Union\",\"url\":\"https:\\/\\/mas.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(134,'Fidelity NetBenefits','558',1,'{\"fid\":\"8288\",\"org\":\"nbofx.fidelity.com\",\"url\":\"https:\\/\\/nbofx.fidelity.com\\/netbenefits\\/ofx\\/download\"}'),(135,'Fall River Municipal CU','559',1,'{\"fid\":\"211382591\",\"org\":\"Fall River Municipal CU\",\"url\":\"https:\\/\\/fal.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(136,'University Credit Union','560',1,'{\"fid\":\"267077850\",\"org\":\"University Credit Union\",\"url\":\"https:\\/\\/umc.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(137,'Dominion Credit Union','561',1,'{\"fid\":\"251082644\",\"org\":\"Dominion Credit Union\",\"url\":\"https:\\/\\/dom.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(138,'HFS Federal Credit Union','562',1,'{\"fid\":\"321378660\",\"org\":\"HFS Federal Credit Union\",\"url\":\"https:\\/\\/hfs.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(139,'IronStone Bank','563',1,'{\"fid\":\"5012\",\"org\":\"Atlantic States Bank\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/5012.ofxgp\"}'),(140,'Utah Community Credit Union','564',1,'{\"fid\":\"324377820\",\"org\":\"Utah Community Credit Union\",\"url\":\"https:\\/\\/ofx.uccu.com\\/scripts\\/isaofx.dll\"}'),(141,'OptionsXpress, Inc','565',1,'{\"fid\":\"10876\",\"org\":\"10876\",\"url\":\"https:\\/\\/ofx.optionsxpress.com\\/cgi-bin\\/ox.exe\"}'),(142,'Prudential Retirement','567',1,'{\"fid\":\"1271\",\"org\":\"Prudential Retirement Services\",\"url\":\"https:\\/\\/ofx.prudential.com\\/eftxweb\\/EFTXWebRedirector\"}'),(143,'Wells Fargo Investments, LLC','568',1,'{\"fid\":\"10762\",\"org\":\"wellsfargo.com\",\"url\":\"https:\\/\\/invmnt.wellsfargo.com\\/inv\\/directConnect\"}'),(144,'Penson Financial Services','570',1,'{\"fid\":\"10780\",\"org\":\"Penson Financial Services Inc\",\"url\":\"https:\\/\\/ofx.penson.com\"}'),(145,'Tri Boro Federal Credit Union','571',1,'{\"fid\":\"243382747\",\"org\":\"Tri Boro Federal Credit Union\",\"url\":\"https:\\/\\/tri.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(146,'Hewitt Associates LLC','572',1,'{\"fid\":\"242\",\"org\":\"hewitt.com\",\"url\":\"https:\\/\\/seven.was.hewitt.com\\/eftxweb\\/access.ofx\"}'),(147,'Delta Community Credit Union','573',1,'{\"fid\":\"3328\",\"org\":\"decu.org\",\"url\":\"https:\\/\\/appweb.deltacommunitycu.com\\/ofxroot\\/directtocore.asp\"}'),(148,'Huntington National Bank','574',1,'{\"fid\":\"3701\",\"org\":\"Huntington\",\"url\":\"https:\\/\\/onlinebanking.huntington.com\\/scripts\\/serverext.dll\"}'),(149,'WSECU','575',1,'{\"fid\":\"325181028\",\"org\":\"WSECU\",\"url\":\"https:\\/\\/ssl3.wsecu.org\\/ofxserver\\/ofxsrvr.dll\"}'),(150,'Baton Rouge City Parish Emp FCU','576',1,'{\"fid\":\"265473333\",\"org\":\"Baton Rouge City Parish EFCU\",\"url\":\"https:\\/\\/bat.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(151,'Schools Financial Credit Union','577',1,'{\"fid\":\"90001\",\"org\":\"Teknowledge\",\"url\":\"https:\\/\\/ofx.schools.org\\/TekPortalOFX\\/servlet\\/TP_OFX_Controller\"}'),(152,'Charles Schwab Bank, N.A.','578',1,'{\"fid\":\"101\",\"org\":\"ISC\",\"url\":\"https:\\/\\/ofx.schwab.com\\/bankcgi_dev\\/ofx_server\"}'),(153,'NW Preferred Federal Credit Union','579',1,'{\"fid\":\"323076575\",\"org\":\"NW Preferred FCU\",\"url\":\"https:\\/\\/nwf.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(154,'Camino FCU','580',1,'{\"fid\":\"322279975\",\"org\":\"Camino FCU\",\"url\":\"https:\\/\\/homebanking.caminofcu.org\\/isaofx\\/isaofx.dll\"}'),(155,'Novartis Federal Credit Union','581',1,'{\"fid\":\"221278556\",\"org\":\"Novartis FCU\",\"url\":\"https:\\/\\/cib.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(156,'U.S. First FCU','582',1,'{\"fid\":\"321076289\",\"org\":\"US First FCU\",\"url\":\"https:\\/\\/uff.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(157,'FAA Technical Center FCU','583',1,'{\"fid\":\"231277440\",\"org\":\"FAA Technical Center FCU\",\"url\":\"https:\\/\\/ftc.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(158,'Municipal Employees Credit Union of Baltimore, Inc.','584',1,'{\"fid\":\"252076468\",\"org\":\"Municipal ECU of Baltimore,Inc.\",\"url\":\"https:\\/\\/mec.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(159,'Day Air Credit Union','585',1,'{\"fid\":\"242277808\",\"org\":\"Day Air Credit Union\",\"url\":\"https:\\/\\/pcu.dayair.org\\/scripts\\/isaofx.dll\"}'),(160,'Texas State Bank - McAllen','586',1,'{\"fid\":\"114909013\",\"org\":\"Texas State Bank\",\"url\":\"https:\\/\\/www.tsb-a.com\\/OFXServer\\/ofxsrvr.dll\"}'),(161,'OCTFCU','587',1,'{\"fid\":\"17600\",\"org\":\"OCTFCU\",\"url\":\"https:\\/\\/ofx.octfcu.org\"}'),(162,'Hawaii State FCU','588',1,'{\"fid\":\"321379041\",\"org\":\"Hawaii State FCU\",\"url\":\"https:\\/\\/hse.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(163,'Community First Credit Union','592',1,'{\"fid\":\"275982801\",\"org\":\"Community First Credit Union\",\"url\":\"https:\\/\\/pcu.communityfirstcu.org\\/scripts\\/isaofx.dll\"}'),(164,'MTC Federal Credit Union','593',1,'{\"fid\":\"053285173\",\"org\":\"MTC Federal Credit Union\",\"url\":\"https:\\/\\/mic.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(165,'Home Federal Savings Bank(MN/IA)','594',1,'{\"fid\":\"291270050\",\"org\":\"VOneTwentySevenG\",\"url\":\"https:\\/\\/ofx1.evault.ws\\/ofxserver\\/ofxsrvr.dll\"}'),(166,'Reliant Community Credit Union','595',1,'{\"fid\":\"222382438\",\"org\":\"W.C.T.A Federal Credit Union\",\"url\":\"https:\\/\\/wct.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(167,'Patriots Federal Credit Union','596',1,'{\"fid\":\"322281963\",\"org\":\"PAT FCU\",\"url\":\"https:\\/\\/pat.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(168,'SafeAmerica Credit Union','597',1,'{\"fid\":\"321171757\",\"org\":\"SafeAmerica Credit Union\",\"url\":\"https:\\/\\/saf.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(169,'Mayo Employees Federal Credit Union','598',1,'{\"fid\":\"291975478\",\"org\":\"Mayo Employees FCU\",\"url\":\"https:\\/\\/homebank.mayocreditunion.org\\/ofx\\/ofx.dll\"}'),(170,'FivePoint Credit Union','599',1,'{\"fid\":\"313187571\",\"org\":\"FivePoint Credit Union\",\"url\":\"https:\\/\\/tfcu-nfuse01.texacocommunity.org\\/internetconnector\\/isaofx.dll\"}'),(171,'Community Resource Bank','600',1,'{\"fid\":\"091917160\",\"org\":\"CNB\",\"url\":\"https:\\/\\/www.cnbinternet.com\\/OFXServer\\/ofxsrvr.dll\"}'),(172,'Security 1st FCU','601',1,'{\"fid\":\"314986292\",\"org\":\"Security 1st FCU\",\"url\":\"https:\\/\\/sec.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(173,'First Alliance Credit Union','602',1,'{\"fid\":\"291975481\",\"org\":\"First Alliance Credit Union\",\"url\":\"https:\\/\\/fia.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(174,'Billings Federal Credit Union','603',1,'{\"fid\":\"6217\",\"org\":\"Billings Federal Credit Union\",\"url\":\"https:\\/\\/bfcuonline.billingsfcu.org\\/ofx\\/ofx.dll\"}'),(175,'Windward Community FCU','604',1,'{\"fid\":\"321380315\",\"org\":\"Windward Community FCU\",\"url\":\"https:\\/\\/wwc.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(176,'Siouxland Federal Credit Union','606',1,'{\"fid\":\"304982235\",\"org\":\"SIOUXLAND FCU\",\"url\":\"https:\\/\\/sio.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(177,'The Queen\'s Federal Credit Union','607',1,'{\"fid\":\"321379504\",\"org\":\"The Queens Federal Credit Union\",\"url\":\"https:\\/\\/que.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(178,'Edward Jones','608',1,'{\"fid\":\"823\",\"org\":\"Edward Jones\",\"url\":\"https:\\/\\/ofx.edwardjones.com\"}'),(179,'Merck Sharp&Dohme FCU','609',1,'{\"fid\":\"231386645\",\"org\":\"MERCK, SHARPE&DOHME FCU\",\"url\":\"https:\\/\\/msd.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(180,'Credit Union 1 - IL','610',1,'{\"fid\":\"271188081\",\"org\":\"Credit Union 1\",\"url\":\"https:\\/\\/pcu.creditunion1.org\\/scripts\\/isaofx.dll\"}'),(181,'Bossier Federal Credit Union','611',1,'{\"fid\":\"311175129\",\"org\":\"Bossier Federal Credit Union\",\"url\":\"https:\\/\\/bos.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(182,'First Florida Credit Union','612',1,'{\"fid\":\"263079014\",\"org\":\"First Llorida Credit Union\",\"url\":\"https:\\/\\/pcu2.gecuf.org\\/scripts\\/isaofx.dll\"}'),(183,'NorthEast Alliance FCU','613',1,'{\"fid\":\"221982130\",\"org\":\"NorthEast Alliance FCU\",\"url\":\"https:\\/\\/nea.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(184,'ShareBuilder','614',1,'{\"fid\":\"5575\",\"org\":\"ShareBuilder\",\"url\":\"https:\\/\\/ofx.sharebuilder.com\"}'),(185,'Weitz Funds','616',1,'{\"fid\":\"weitz.com\",\"org\":\"weitz.com\",\"url\":\"https:\\/\\/www3.financialtrans.com\\/tf\\/OFXServer?tx=OFXController&cz=702110804131918&cl=52204081925\"}'),(186,'JPMorgan Retirement Plan Services','617',1,'{\"fid\":\"6313\",\"org\":\"JPMORGAN\",\"url\":\"https:\\/\\/ofx.retireonline.com\\/eftxweb\\/access.ofx\"}'),(187,'Credit Union ONE','618',1,'{\"fid\":\"14412\",\"org\":\"Credit Union ONE\",\"url\":\"https:\\/\\/cuhome.cuone.org\\/ofx\\/ofx.dll\"}'),(188,'Salt Lake City Credit Union','619',1,'{\"fid\":\"324079186\",\"org\":\"Salt Lake City Credit Union\",\"url\":\"https:\\/\\/slc.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(189,'First Southwest Company','620',1,'{\"fid\":\"7048\",\"org\":\"AFS\",\"url\":\"https:\\/\\/fswofx.automatedfinancial.com\"}'),(190,'Wells Fargo Trust-Investment Mgt','622',1,'{\"fid\":\"6955\",\"org\":\"Wells Fargo Trust\",\"url\":\"https:\\/\\/trust.wellsfargo.com\\/trust\\/directConnect\"}'),(191,'Scottrade, Inc.','623',1,'{\"fid\":\"777\",\"org\":\"Scottrade\",\"url\":\"https:\\/\\/ofxstl.scottsave.com\"}'),(192,'Silver State Schools CU','624',1,'{\"fid\":\"322484265\",\"org\":\"SSSCU\",\"url\":\"https:\\/\\/www.silverstatecu.com\\/OFXServer\\/ofxsrvr.dll\"}'),(193,'VISA Information Source','626',1,'{\"fid\":\"10942\",\"org\":\"VISA\",\"url\":\"https:\\/\\/vis.informationmanagement.visa.com\\/eftxweb\\/access.ofx\"}'),(194,'National City','627',1,'{\"fid\":\"5860\",\"org\":\"NATIONAL CITY\",\"url\":\"https:\\/\\/ofx.nationalcity.com\\/ofx\\/OFXConsumer.aspx\"}'),(195,'Capital One','628',1,'{\"fid\":\"1001\",\"org\":\"Hibernia\",\"url\":\"https:\\/\\/onlinebanking.capitalone.com\\/scripts\\/serverext.dll\"}'),(196,'Citi Credit Card','629',1,'{\"fid\":\"24909\",\"org\":\"Citigroup\",\"url\":\"https:\\/\\/www.accountonline.com\\/cards\\/svc\\/CitiOfxManager.do\"}'),(197,'Zions Bank','630',1,'{\"fid\":\"1115\",\"org\":\"244-3\",\"url\":\"https:\\/\\/quicken.metavante.com\\/ofx\\/OFXServlet\"}'),(198,'Capital One Bank','631',1,'{\"fid\":\"1001\",\"org\":\"Hibernia\",\"url\":\"https:\\/\\/onlinebanking.capitalone.com\\/scripts\\/serverext.dll\"}'),(199,'Redstone Federal Credit Union','633',1,'{\"fid\":\"2143\",\"org\":\"Harland Financial Solutions\",\"url\":\"https:\\/\\/remotebanking.redfcu.org\\/ofx\\/ofx.dll\"}'),(200,'PNC Bank','634',1,'{\"fid\":\"4501\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/04501.ofx\"}'),(201,'Bank of America (California)','635',1,'{\"fid\":\"6805\",\"org\":\"HAN\",\"url\":\"https:\\/\\/ofx.bankofamerica.com\\/cgi-forte\\/ofx?servicename=ofx_2-3&pagename=bofa\"}'),(202,'Chase (credit card) ','636',1,'{\"fid\":\"10898\",\"org\":\"B1\",\"url\":\"https:\\/\\/ofx.chase.com\"}'),(203,'Arizona Federal Credit Union','637',1,'{\"fid\":\"322172797\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(204,'UW Credit Union','638',1,'{\"fid\":\"1001\",\"org\":\"UWCU\",\"url\":\"https:\\/\\/ofx.uwcu.org\\/serverext.dll\"}'),(205,'Bank of America','639',1,'{\"fid\":\"5959\",\"org\":\"HAN\",\"url\":\"https:\\/\\/eftx.bankofamerica.com\\/eftxweb\\/access.ofx\"}'),(206,'Commerce Bank','640',1,'{\"fid\":\"1001\",\"org\":\"CommerceBank\",\"url\":\"https:\\/\\/ofx.tdbank.com\\/scripts\\/serverext.dll\"}'),(207,'Securities America','641',1,'{\"fid\":\"7784\",\"org\":\"Fidelity\",\"url\":\"https:\\/\\/ofx.ibgstreetscape.com:443\"}'),(208,'First Internet Bank of Indiana','642',1,'{\"fid\":\"074014187\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(209,'Alpine Banks of Colorado','643',1,'{\"fid\":\"1451\",\"org\":\"JackHenry\",\"url\":\"https:\\/\\/directline.netteller.com\"}'),(210,'BancFirst','644',1,'{\"fid\":\"103003632\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(211,'Desert Schools Federal Credit Union','645',1,'{\"fid\":\"1001\",\"org\":\"DSFCU\",\"url\":\"https:\\/\\/epal.desertschools.org\\/scripts\\/serverext.dll\"}'),(212,'Kinecta Federal Credit Union','646',1,'{\"fid\":\"322278073\",\"org\":\"KINECTA\",\"url\":\"https:\\/\\/ofx.kinecta.org\\/OFXServer\\/ofxsrvr.dll\"}'),(213,'Boeing Employees Credit Union','647',1,'{\"fid\":\"1001\",\"org\":\"becu\",\"url\":\"https:\\/\\/www.becuonlinebanking.org\\/scripts\\/serverext.dll\"}'),(214,'Capital One Bank - 2','648',1,'{\"fid\":\"1001\",\"org\":\"Hibernia\",\"url\":\"https:\\/\\/onlinebanking.capitalone.com\\/ofx\\/process.ofx\"}'),(215,'Michigan State University Federal CU','649',1,'{\"fid\":\"272479663\",\"org\":\"MSUFCU\",\"url\":\"https:\\/\\/ofx.msufcu.org\\/ofxserver\\/ofxsrvr.dll\"}'),(216,'The Community Bank','650',1,'{\"fid\":\"211371476\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(217,'Sacramento Credit Union','651',1,'{\"fid\":\"1\",\"org\":\"SACRAMENTO CREDIT UNION\",\"url\":\"https:\\/\\/homebank.sactocu.org\\/ofx\\/ofx.dll\"}'),(218,'TD Bank','652',1,'{\"fid\":\"1001\",\"org\":\"CommerceBank\",\"url\":\"https:\\/\\/onlinebanking.tdbank.com\\/scripts\\/serverext.dll\"}'),(219,'Suncoast Schools FCU','653',1,'{\"fid\":\"1001\",\"org\":\"SunCoast\",\"url\":\"https:\\/\\/ofx.suncoastfcu.org\"}'),(220,'Metro Bank','654',1,'{\"fid\":\"9970\",\"org\":\"MTRO\",\"url\":\"https:\\/\\/ofx.mymetrobank.com\\/ofx\\/ofx.ofx\"}'),(221,'First National Bank (Texas)','655',1,'{\"fid\":\"12840\",\"org\":\"JackHenry\",\"url\":\"https:\\/\\/directline.netteller.com\"}'),(222,'Bank of the West','656',1,'{\"fid\":\"5809\",\"org\":\"BancWest Corp\",\"url\":\"https:\\/\\/olbp.bankofthewest.com\\/ofx0002\\/ofx_isapi.dll\"}'),(223,'Mountain America Credit Union','657',1,'{\"fid\":\"324079555\",\"org\":\"MACU\",\"url\":\"https:\\/\\/ofx.macu.org\\/OFXServer\\/ofxsrvr.dll\"}'),(224,'ING DIRECT','658',1,'{\"fid\":\"031176110\",\"org\":\"ING DIRECT\",\"url\":\"https:\\/\\/ofx.ingdirect.com\\/OFX\\/ofx.html\"}'),(225,'Santa Barbara Bank & Trust','659',1,'{\"fid\":\"5524\",\"org\":\"pfm-l3g\",\"url\":\"https:\\/\\/pfm.metavante.com\\/ofx\\/OFXServlet\"}'),(226,'UMB','660',1,'{\"fid\":\"468\",\"org\":\"UMBOFX\",\"url\":\"https:\\/\\/ofx.umb.com\"}'),(227,'Bank Of America(All except CA,WA,&ID ','661',1,'{\"fid\":\"6812\",\"org\":\"HAN\",\"url\":\"Https:\\/\\/ofx.bankofamerica.com\\/cgi-forte\\/fortecgi?servicename=ofx_2-3&pagename=ofx \"}'),(228,'Centra Credit Union2','662',1,'{\"fid\":\"274972883\",\"org\":\"Centra CU\",\"url\":\"https:\\/\\/www.centralink.org\\/scripts\\/isaofx.dll\"}'),(229,'Mainline National Bank','663',1,'{\"fid\":\"9869\",\"org\":\"JackHenry\",\"url\":\"https:\\/\\/directline.netteller.com\"}'),(230,'Citizens Bank','664',1,'{\"fid\":\"4639\",\"org\":\"CheckFree OFX\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/04639.ofxgp\"}'),(231,'USAA Investment Mgmt Co','665',1,'{\"fid\":\"24592\",\"org\":\"USAA\",\"url\":\"https:\\/\\/service2.usaa.com\\/ofx\\/OFXServlet\"}'),(232,'121 Financial Credit Union','666',1,'{\"fid\":\"000001155\",\"org\":\"121 Financial Credit Union\",\"url\":\"https:\\/\\/ppc.121fcu.org\\/scripts\\/isaofx.dll\"}'),(233,'Abbott Laboratories Employee CU','667',1,'{\"fid\":\"35MXN\",\"org\":\"Abbott Laboratories ECU - ALEC\",\"url\":\"https:\\/\\/www.netit.financial-net.com\\/ofx\\/\"}'),(234,'Achieva Credit Union','668',1,'{\"fid\":\"4491\",\"org\":\"Achieva Credit Union\",\"url\":\"https:\\/\\/rbserver.achievacu.com\\/ofx\\/ofx.dll\"}'),(235,'American National Bank','669',1,'{\"fid\":\"4201\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/04201.ofx\"}'),(236,'Andrews Federal Credit Union','670',1,'{\"fid\":\"AFCUSMD\",\"org\":\"FundsXpress\",\"url\":\"https:\\/\\/ofx.fundsxpress.com\\/piles\\/ofx.pile\\/\"}'),(237,'Citi Personal Wealth Management','671',1,'{\"fid\":\"060\",\"org\":\"Citigroup\",\"url\":\"https:\\/\\/uat-ofx.netxclient.inautix.com\\/cgi\\/OFXNetx\"}'),(238,'Bank One (Chicago)','672',1,'{\"fid\":\"1501\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/01501.ofx\"}'),(239,'Bank One (Michigan and Florida)','673',1,'{\"fid\":\"6001\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/06001.ofx\"}'),(240,'Bank of America (Formerly Fleet)','674',1,'{\"fid\":\"1803\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/01803.ofx\"}'),(241,'BankBoston PC Banking','675',1,'{\"fid\":\"1801\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/01801.ofx\"}'),(242,'Beverly Co-Operative Bank','676',1,'{\"fid\":\"531\",\"org\":\"orcc\",\"url\":\"https:\\/\\/www19.onlinebank.com\\/OROFX16Listener\"}'),(243,'Cambridge Portuguese Credit Union','677',1,'{\"fid\":\"983\",\"org\":\"orcc\",\"url\":\"https:\\/\\/www20.onlinebank.com\\/OROFX16Listener\"}'),(244,'Citibank','678',1,'{\"fid\":\"2101\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/02101.ofx\"}'),(245,'Community Bank, N.A.','679',1,'{\"fid\":\"11517\",\"org\":\"JackHenry\",\"url\":\"https:\\/\\/directline2.netteller.com\"}'),(246,'Consumers Credit Union','680',1,'{\"fid\":\"12541\",\"org\":\"Consumers Credit Union\",\"url\":\"https:\\/\\/ofx.lanxtra.com\\/ofx\\/servlet\\/Teller\"}'),(247,'CPM Federal Credit Union','681',1,'{\"fid\":\"253279536\",\"org\":\"USERS, Inc.\",\"url\":\"https:\\/\\/cpm.usersonlnet.com\\/scripts\\/isaofx.dll\"}'),(248,'DATCU','682',1,'{\"fid\":\"311980725\",\"org\":\"DATCU\",\"url\":\"https:\\/\\/online.datcu.coop\\/ofxserver\\/ofxsrvr.dll\"}'),(249,'Denver Community Federal Credit Union','683',1,'{\"fid\":\"10524\",\"org\":\"Denver Community FCU\",\"url\":\"https:\\/\\/pccu.dcfcu.coop\\/ofx\\/ofx.dll\"}'),(250,'Discover Platinum','684',1,'{\"fid\":\"7102\",\"org\":\"Discover Financial Services\",\"url\":\"https:\\/\\/ofx.discovercard.com\\/\"}'),(251,'EAB','685',1,'{\"fid\":\"6505\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/06505.ofx\"}'),(252,'FAA Credit Union','686',1,'{\"fid\":\"114\",\"org\":\"FAA Credit Union\",\"url\":\"https:\\/\\/flightline.faaecu.org\\/ofx\\/ofx.dll\"}'),(253,'Fairwinds Credit Union','687',1,'{\"fid\":\"4842\",\"org\":\"OSI 2\",\"url\":\"https:\\/\\/OFX.opensolutionsTOC.com\\/eftxweb\\/access.ofx\"}'),(254,'FedChoice FCU','688',1,'{\"fid\":\"254074785\",\"org\":\"FEDCHOICE\",\"url\":\"https:\\/\\/ofx.fedchoice.org\\/ofxserver\\/ofxsrvr.dll\"}'),(255,'First Clearing, LLC','689',1,'{\"fid\":\"10033\",\"org\":\"First Clearing, LLC\",\"url\":\"https:\\/\\/pfmpw.wachovia.com\\/cgi-forte\\/fortecgi?servicename=ofxbrk&pagename=PFM\"}'),(256,'First Citizens','690',1,'{\"fid\":\"1849\",\"org\":\"First Citizens\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/01849.ofx\"}'),(257,'First Hawaiian Bank','691',1,'{\"fid\":\"3501\",\"org\":\"BancWest Corp\",\"url\":\"https:\\/\\/olbp.fhb.com\\/ofx0001\\/ofx_isapi.dll\"}'),(258,'First National Bank of St. Louis','692',1,'{\"fid\":\"162\",\"org\":\"81004601\",\"url\":\"https:\\/\\/ofx.centralbancompany.com\\/ofxserver\\/ofxsrvr.dll\"}'),(259,'First Interstate Bank','693',1,'{\"fid\":\"092901683\",\"org\":\"FIB\",\"url\":\"https:\\/\\/ofx.firstinterstatebank.com\\/OFXServer\\/ofxsrvr.dll\"}'),(260,'Goldman Sachs','694',1,'{\"fid\":\"1234\",\"org\":\"gs.com\",\"url\":\"https:\\/\\/portfolio-ofx.gs.com:446\\/ofx\\/ofx.eftx\"}'),(261,'Hudson Valley FCU','695',1,'{\"fid\":\"10767\",\"org\":\"Hudson Valley FCU\",\"url\":\"https:\\/\\/internetbanking.hvfcu.org\\/ofx\\/ofx.dll\"}'),(262,'IBM Southeast Employees Federal Credit Union','696',1,'{\"fid\":\"1779\",\"org\":\"IBM Southeast EFCU\",\"url\":\"https:\\/\\/rb.ibmsecu.org\\/ofx\\/ofx.dll\"}'),(263,'Insight CU','697',1,'{\"fid\":\"10764\",\"org\":\"Insight Credit Union\",\"url\":\"https:\\/\\/secure.insightcreditunion.com\\/ofx\\/ofx.dll\"}'),(264,'Janney Montgomery Scott LLC','698',1,'{\"fid\":\"11326\",\"org\":\"AFS\",\"url\":\"https:\\/\\/jmsofx.automatedfinancial.com\"}'),(265,'JSC Federal Credit Union','699',1,'{\"fid\":\"10491\",\"org\":\"JSC Federal Credit Union\",\"url\":\"https:\\/\\/starpclegacy.jscfcu.org\\/ofx\\/ofx.dll\"}'),(266,'J.P. Morgan','700',1,'{\"fid\":\"4701\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/04701.ofx\"}'),(267,'J.P. Morgan Clearing Corp.','701',1,'{\"fid\":\"7315\",\"org\":\"GCS\",\"url\":\"https:\\/\\/ofxgcs.toolkit.clearco.com\"}'),(268,'M & T Bank','702',1,'{\"fid\":\"2601\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/02601.ofx\"}'),(269,'Marquette Banks','703',1,'{\"fid\":\"1301\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/01301.ofx\"}'),(270,'Mercer','704',1,'{\"fid\":\"8007527525\",\"org\":\"PutnamDefinedContributions\",\"url\":\"https:\\/\\/ofx.mercerhrs.com\\/eftxweb\\/access.ofx\"}'),(271,'Merrill Lynch Online Payment','705',1,'{\"fid\":\"7301\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/07301.ofx\"}'),(272,'Missoula Federal Credit Union','706',1,'{\"fid\":\"5097\",\"org\":\"Missoula Federal Credit Union\",\"url\":\"https:\\/\\/secure.missoulafcu.org\\/ofx\\/ofx.dll\"}'),(273,'Morgan Stanley (Smith Barney)','707',1,'{\"fid\":\"5207\",\"org\":\"Smithbarney.com\",\"url\":\"https:\\/\\/ofx.smithbarney.com\\/app-bin\\/ofx\\/servlets\\/access.ofx\"}'),(274,'Nevada State Bank - OLD','708',1,'{\"fid\":\"5401\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/05401.ofx\"}'),(275,'New England Federal Credit Union','709',1,'{\"fid\":\"2104\",\"org\":\"New England Federal Credit Union\",\"url\":\"https:\\/\\/pcaccess.nefcu.com\\/ofx\\/ofx.dll\"}'),(276,'Norwest','710',1,'{\"fid\":\"4601\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/04601.ofx\"}'),(277,'Oppenheimer & Co. Inc.','711',1,'{\"fid\":\"125\",\"org\":\"Oppenheimer\",\"url\":\"https:\\/\\/ofx.opco.com\\/eftxweb\\/access.ofx\"}'),(278,'Oregon College Savings Plan','712',1,'{\"fid\":\"51498\",\"org\":\"tiaaoregon\",\"url\":\"https:\\/\\/ofx3.financialtrans.com\\/tf\\/OFXServer?tx=OFXController&cz=702110804131918&cl=b1908000027141704061413\"}'),(279,'RBC Dain Rauscher','713',1,'{\"fid\":\"8035\",\"org\":\"RBC Dain Rauscher\",\"url\":\"https:\\/\\/ofx.rbcdain.com\\/\"}'),(280,'Robert W. Baird & Co.','714',1,'{\"fid\":\"1109\",\"org\":\"Robert W. Baird & Co.\",\"url\":\"https:\\/\\/ofx.rwbaird.com\"}'),(281,'Sears Card','715',1,'{\"fid\":\"26810\",\"org\":\"CITIGROUP\",\"url\":\"https:\\/\\/secureofx.bankhost.com\\/tuxofx\\/cgi-bin\\/cgi_chip\"}'),(282,'South Trust Bank','716',1,'{\"fid\":\"6101\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/06101.ofx\"}'),(283,'Standard Federal Bank','717',1,'{\"fid\":\"6507\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/06507.ofx\"}'),(284,'United California Bank','718',1,'{\"fid\":\"2701\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/fip\\/genesis\\/prod\\/02701.ofx\"}'),(285,'United Federal CU - PowerLink','719',1,'{\"fid\":\"1908\",\"org\":\"United Federal Credit Union\",\"url\":\"https:\\/\\/remotebanking.unitedfcu.com\\/ofx\\/ofx.dll\"}'),(286,'VALIC','720',1,'{\"fid\":\"77019\",\"org\":\"valic.com\",\"url\":\"https:\\/\\/ofx.valic.com\\/eftxweb\\/access.ofx\"}'),(287,'Van Kampen Funds, Inc.','721',1,'{\"fid\":\"3625\",\"org\":\"Van Kampen Funds, Inc.\",\"url\":\"https:\\/\\/ofx3.financialtrans.com\\/tf\\/OFXServer?tx=OFXController&cz=702110804131918&cl=9210013100012150413\"}'),(288,'Vanguard Group','722',1,'{\"fid\":\"1358\",\"org\":\"The Vanguard Group\",\"url\":\"https:\\/\\/vesnc.vanguard.com\\/us\\/OfxProfileServlet\"}'),(289,'Velocity Credit Union','723',1,'{\"fid\":\"9909\",\"org\":\"Velocity Credit Union\",\"url\":\"https:\\/\\/rbserver.velocitycu.com\\/ofx\\/ofx.dll\"}'),(290,'Waddell & Reed - Ivy Funds','724',1,'{\"fid\":\"49623\",\"org\":\"waddell\",\"url\":\"https:\\/\\/ofx3.financialtrans.com\\/tf\\/OFXServer?tx=OFXController&cz=702110804131918&cl=722000303041111\"}'),(291,'Umpqua Bank','725',1,'{\"fid\":\"1001\",\"org\":\"Umpqua\",\"url\":\"https:\\/\\/ofx.umpquabank.com\\/ofx\\/process.ofx\"}'),(292,'Discover Bank','726',1,'{\"fid\":\"12610\",\"org\":\"Discover Bank\",\"url\":\"https:\\/\\/ofx.discovercard.com\"}'),(293,'Elevations Credit Union','727',1,'{\"fid\":\"1001\",\"org\":\"uocfcu\",\"url\":\"https:\\/\\/ofx.elevationscu.com\\/scripts\\/serverext.dll\"}'),(294,'Kitsap Community Credit Union','728',1,'{\"fid\":\"325180223\",\"org\":\"Kitsap Community Federal Credit\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(295,'Charles Schwab Retirement','729',1,'{\"fid\":\"1234\",\"org\":\"SchwabRPS\",\"url\":\"https:\\/\\/ofx.schwab.com\\/cgi_dev\\/ofx_server\"}'),(296,'Charles Schwab Retirement Plan Services','730',1,'{\"fid\":\"1234\",\"org\":\"SchwabRPS\",\"url\":\"https:\\/\\/ofx.schwab.com\\/cgi_dev\\/ofx_server\"}'),(297,'First Tech Federal Credit Union','731',1,'{\"fid\":\"3169\",\"org\":\"First Tech Federal Credit Union\",\"url\":\"https:\\/\\/ofx.firsttechfed.com\"}'),(298,'Affinity Plus Federal Credit Union','732',1,'{\"fid\":\"75\",\"org\":\"Affinity Plus FCU\",\"url\":\"https:\\/\\/hb.affinityplus.org\\/ofx\\/ofx.dll\"}'),(299,'Bank of George','733',1,'{\"fid\":\"122402366\",\"org\":\"122402366\",\"url\":\"https:\\/\\/ofx.internet-ebanking.com\\/CCOFXServer\\/servlet\\/TP_OFX_Controller\"}'),(300,'Franklin Templeton Investments','734',1,'{\"fid\":\"9444\",\"org\":\"franklintempleton.com\",\"url\":\"https:\\/\\/ofx.franklintempleton.com\\/eftxweb\\/access.ofx\"}'),(301,'ING Institutional Plan Services ','735',1,'{\"fid\":\"1289\",\"org\":\"ing-usa.com\",\"url\":\"https:\\/\\/ofx.ingplans.com\\/ofx\\/Server\"}'),(302,'Sterne Agee','736',1,'{\"fid\":\"2170\",\"org\":\"AFS\",\"url\":\"https:\\/\\/salofx.automatedfinancial.com\"}'),(303,'Wells Fargo Advisors','737',1,'{\"fid\":\"12748\",\"org\":\"WF\",\"url\":\"https:\\/\\/ofxdc.wellsfargo.com\\/ofxbrokerage\\/process.ofx\"}'),(304,'Community 1st Credit Union','738',1,'{\"fid\":\"325082017\",\"org\":\"Community 1st Credit Union\",\"url\":\"https:\\/\\/ib.comm1stcu.org\\/scripts\\/isaofx.dll\"}'),(305,'J.P. Morgan Private Banking','740',1,'{\"fid\":\"0417\",\"org\":\"jpmorgan.com\",\"url\":\"https:\\/\\/ofx.jpmorgan.com\\/jpmredirector\"}'),(306,'Northwest Community CU','741',1,'{\"fid\":\"1948\",\"org\":\"Cavion\",\"url\":\"https:\\/\\/ofx.lanxtra.com\\/ofx\\/servlet\\/Teller\"}'),(307,'North Carolina State Employees Credit Union','742',1,'{\"fid\":\"1001\",\"org\":\"SECU\",\"url\":\"https:\\/\\/onlineaccess.ncsecu.org\\/secuofx\\/secu.ofx \"}'),(308,'International Bank of Commerce','743',1,'{\"fid\":\"1001\",\"org\":\"IBC\",\"url\":\"https:\\/\\/ibcbankonline2.ibc.com\\/scripts\\/serverext.dll\"}'),(309,'RaboBank America','744',1,'{\"fid\":\"11540\",\"org\":\"RBB\",\"url\":\"https:\\/\\/ofx.rabobankamerica.com\\/ofx\\/process.ofx\"}'),(310,'Hughes Federal Credit Union','745',1,'{\"fid\":\"1951\",\"org\":\"Cavion\",\"url\":\"https:\\/\\/ofx.lanxtra.com\\/ofx\\/servlet\\/Teller\"}'),(311,'Apple FCU','746',1,'{\"fid\":\"256078514\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(312,'Chemical Bank','747',1,'{\"fid\":\"072410013\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(313,'Local Government Federal Credit Union','748',1,'{\"fid\":\"1001\",\"org\":\"SECU\",\"url\":\"https:\\/\\/onlineaccess.ncsecu.org\\/lgfcuofx\\/lgfcu.ofx\"}'),(314,'Wells Fargo Bank','749',1,'{\"fid\":\"3000\",\"org\":\"WF\",\"url\":\"https:\\/\\/ofxdc.wellsfargo.com\\/ofx\\/process.ofx\"}'),(315,'Schwab Retirement Plan Services','750',1,'{\"fid\":\"11811\",\"org\":\"The 401k Company\",\"url\":\"https:\\/\\/ofx1.401kaccess.com\"}'),(316,'Southern Community Bank and Trust (SCB&T)','751',1,'{\"fid\":\"053112097\",\"org\":\"MOneFortyEight\",\"url\":\"https:\\/\\/ofx1.evault.ws\\/OFXServer\\/ofxsrvr.dll\"}'),(317,'Elevations Credit Union IB WC-DC','752',1,'{\"fid\":\"307074580\",\"org\":\"uofcfcu\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx \"}'),(318,'Credit Suisse Securities USA LLC','753',1,'{\"fid\":\"001\",\"org\":\"Credit Suisse Securities USA LLC\",\"url\":\"https:\\/\\/ofx.netxclient.com\\/cgi\\/OFXNetx\"}'),(319,'North Country FCU','754',1,'{\"fid\":\"211691004\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(320,'South Carolina Bank and Trust','755',1,'{\"fid\":\"053200983\",\"org\":\"MZeroOneZeroSCBT\",\"url\":\"https:\\/\\/ofx1.evault.ws\\/ofxserver\\/ofxsrvr.dll\"}'),(321,'Wings Financial','756',1,'{\"fid\":\"296076152\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(322,'Haverhill Bank','757',1,'{\"fid\":\"93\",\"org\":\"orcc\",\"url\":\"https:\\/\\/www20.onlinebank.com\\/OROFX16Listener\"}'),(323,'Mission Federal Credit Union','758',1,'{\"fid\":\"1001\",\"org\":\"mission\",\"url\":\"https:\\/\\/missionlink.missionfcu.org\\/scripts\\/serverext.dll\"}'),(324,'Southwest Missouri Bank','759',1,'{\"fid\":\"101203641\",\"org\":\"Jack Henry\",\"url\":\"https:\\/\\/directline.netteller.com\"}'),(325,'Cambridge Savings Bank','760',1,'{\"fid\":\"211371120\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(326,'NetxClient UAT','761',1,'{\"fid\":\"1023\",\"org\":\"NetxClient\",\"url\":\"https:\\/\\/uat-ofx.netxclient.inautix.com\\/cgi\\/OFXNetx\"}'),(327,'bankfinancial','762',1,'{\"fid\":\"271972899\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(328,'AXA Equitable','763',1,'{\"fid\":\"7199\",\"org\":\"AXA\",\"url\":\"https:\\/\\/ofx.netxclient.com\\/cgi\\/OFXNetx\"}'),(329,'Premier America Credit Union','764',1,'{\"fid\":\"322283990\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(330,'Bank of America - 5959','765',1,'{\"fid\":\"5959\",\"org\":\"HAN\",\"url\":\"https:\\/\\/ofx.bankofamerica.com\\/cgi-forte\\/fortecgi?servicename=ofx_2-3&pagename=ofx\"}'),(331,'First Command Bank','766',1,'{\"fid\":\"188\",\"org\":\"First Command Bank\",\"url\":\"https:\\/\\/www19.onlinebank.com\\/OROFX16Listener\"}'),(332,'TIAA-CREF','767',1,'{\"fid\":\"041\",\"org\":\"tiaa-cref.org\",\"url\":\"https:\\/\\/ofx.netxclient.com\\/cgi\\/OFXNetx\"}'),(333,'Citizens National Bank','768',1,'{\"fid\":\"111903151\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(334,'Tower Federal Credit Union','769',1,'{\"fid\":\"255077370\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(335,'First Republic Bank','770',1,'{\"fid\":\"321081669\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(336,'Texans Credit Union','771',1,'{\"fid\":\"-1\",\"org\":\"TexansCU\",\"url\":\"https:\\/\\/www.netit.financial-net.com\\/ofx\"}'),(337,'AltaOne','772',1,'{\"fid\":\"322274462\",\"org\":\"AltaOneFCU\",\"url\":\"https:\\/\\/msconline.altaone.net\\/scripts\\/isaofx.dll\"}'),(338,'CenterState Bank','773',1,'{\"fid\":\"1942\",\"org\":\"ORCC\",\"url\":\"https:\\/\\/www20.onlinebank.com\\/OROFX16Listener\"}'),(339,'5 Star Bank','774',1,'{\"fid\":\"307087713\",\"org\":\"5 Star Bank\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(340,'Belmont Savings Bank','775',1,'{\"fid\":\"211371764\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(341,'UNIVERSITY & STATE EMPLOYEES CU','776',1,'{\"fid\":\"322281691\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(342,'Wells Fargo Bank 2013','777',1,'{\"fid\":\"3001\",\"org\":\"Wells Fargo\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/3001.ofxgp\"}'),(343,'The Golden1 Credit Union','778',1,'{\"fid\":\"1001\",\"org\":\"Golden1\",\"url\":\"https:\\/\\/homebanking.golden1.com\\/scripts\\/serverext.dll\"}'),(344,'Woodsboro Bank','779',1,'{\"fid\":\"7479\",\"org\":\"JackHenry\",\"url\":\"https:\\/\\/directline.netteller.com\\/\"}'),(345,'Sandia Laboratory Federal Credit Union','780',1,'{\"fid\":\"1001\",\"org\":\"SLFCU\",\"url\":\"https:\\/\\/ofx-prod.slfcu.org\\/ofx\\/process.ofx \"}'),(346,'Oregon Community Credit Union','781',1,'{\"fid\":\"2077\",\"org\":\"ORCC\",\"url\":\"https:\\/\\/www20.onlinebank.com\\/OROFX16Listener\"}'),(347,'Advantis Credit Union','782',1,'{\"fid\":\"323075097\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(348,'Capital One 360','783',1,'{\"fid\":\"031176110\",\"org\":\"ING DIRECT\",\"url\":\"https:\\/\\/ofx.capitalone360.com\\/OFX\\/ofx.html\"}'),(349,'Flagstar Bank','784',1,'{\"fid\":\"272471852\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(350,'Arizona State Credit Union','785',1,'{\"fid\":\"322172496\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(351,'AmegyBank','786',1,'{\"fid\":\"1165\",\"org\":\"292-3\",\"url\":\"https:\\/\\/pfm.metavante.com\\/ofx\\/OFXServlet\"}'),(352,'Bank of Internet, USA','787',1,'{\"fid\":\"122287251\",\"org\":\"Bank of Internet\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(353,'Amplify Federal Credit Union','788',1,'{\"fid\":\"1\",\"org\":\"Harland Financial Solutions\",\"url\":\"https:\\/\\/ezonline.goamplify.com\\/ofx\\/ofx.dll\"}'),(354,'Capitol Federal Savings Bank','789',1,'{\"fid\":\"1001\",\"org\":\"CapFed\",\"url\":\"https:\\/\\/ofx-prod.capfed.com\\/ofx\\/process.ofx\"}'),(355,'Bank of America - access.ofx','790',1,'{\"fid\":\"5959\",\"org\":\"HAN\",\"url\":\"https:\\/\\/eftx.bankofamerica.com\\/eftxweb\\/access.ofx\"}'),(356,'SVB','791',1,'{\"fid\":\"944\",\"org\":\"SVB\",\"url\":\"https:\\/\\/ofx.svbconnect.com\\/eftxweb\\/access.ofx\"}'),(357,'Iinvestor360','792',1,'{\"fid\":\"7784\",\"org\":\"Fidelity\",\"url\":\"https:\\/\\/www.investor360.net\\/OFX\\/FinService.asmx\\/GetData\"}'),(358,'Sound CU','793',1,'{\"fid\":\"325183220\",\"org\":\"SOUNDCUDC\",\"url\":\"https:\\/\\/mb.soundcu.com\\/OFXServer\\/ofxsrvr.dll\"}'),(359,'Tangerine (Canada)','794',1,'{\"fid\":\"10951\",\"org\":\"TangerineBank\",\"url\":\"https:\\/\\/ofx.tangerine.ca\"}'),(360,'First Tennessee','795',1,'{\"fid\":\"2250\",\"org\":\"Online Financial Services \",\"url\":\"https:\\/\\/ofx.firsttennessee.com\\/ofx\\/ofx_isapi.dll \"}'),(361,'Alaska Air Visa (Bank of America)','796',1,'{\"fid\":\"1142\",\"org\":\"BofA\",\"url\":\"https:\\/\\/akairvisa.iglooware.com\\/visa.php\"}'),(362,'TIAA-CREF Retirement Services','797',1,'{\"fid\":\"1304\",\"org\":\"TIAA-CREF\",\"url\":\"https:\\/\\/ofx-service.tiaa-cref.org\\/public\\/ofx\"}'),(363,'Bofi federal bank','798',1,'{\"fid\":\"122287251\",\"org\":\"Bofi Federal Bank - Business\",\"url\":\"https:\\/\\/directline.netteller.com\"}'),(364,'Vanguard','799',1,'{\"fid\":\"15103\",\"org\":\"Vanguard\",\"url\":\"https:\\/\\/vesnc.vanguard.com\\/us\\/OfxDirectConnectServlet\"}'),(365,'Wright Patt CU','800',1,'{\"fid\":\"242279408\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(366,'Technology Credit Union','801',1,'{\"fid\":\"15079\",\"org\":\"TECHCUDC\",\"url\":\"https:\\/\\/m.techcu.com\\/ofxserver\\/ofxsrvr.dll\"}'),(367,'Capital One Bank (after 12-15-13)','802',1,'{\"fid\":\"1001\",\"org\":\"Capital One\",\"url\":\"https:\\/\\/ofx.capitalone.com\\/ofx\\/103\\/process.ofx\"}'),(368,'Bancorpsouth','803',1,'{\"fid\":\"1001\",\"org\":\"BXS\",\"url\":\"https:\\/\\/ofx-prod.bancorpsouthonline.com\\/ofx\\/process.ofx\"}'),(369,'Monterey Credit Union','804',1,'{\"fid\":\"2059\",\"org\":\"orcc\",\"url\":\"https:\\/\\/www20.onlinebank.com\\/OROFX16Listener\"}'),(370,'D. A. Davidson','805',1,'{\"fid\":\"59401\",\"org\":\"dadco.com\",\"url\":\"https:\\/\\/pfm.davidsoncompanies.com\\/eftxweb\\/access.ofx\"}'),(371,'Morgan Stanley ClientServ - Quicken Win Format','806',1,'{\"fid\":\"1235\",\"org\":\"msdw.com\",\"url\":\"https:\\/\\/ofx.morganstanleyclientserv.com\\/ofx\\/QuickenWinProfile.ofx\"}'),(372,'Star One Credit Union','807',1,'{\"fid\":\"321177968\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(373,'Scottrade Brokerage','808',1,'{\"fid\":\"777\",\"org\":\"Scottrade\",\"url\":\"https:\\/\\/ofx.scottrade.com\"}'),(374,'Mutual Bank','809',1,'{\"fid\":\"88\",\"org\":\"ORCC\",\"url\":\"https:\\/\\/www20.onlinebank.com\\/OROFX16Listener\"}'),(375,'Affinity Plus Federal Credit Union-New','810',1,'{\"fid\":\"15268\",\"org\":\"Affinity Plus Federal Credit Uni\",\"url\":\"https:\\/\\/mobile.affinityplus.org\\/OFX\\/OFXServer.aspx\"}'),(376,'Suncoast Credit Union','811',1,'{\"fid\":\"15469\",\"org\":\"SunCoast\",\"url\":\"https:\\/\\/ofx.suncoastcreditunion.com\"}'),(377,'Think Mutual Bank','812',1,'{\"fid\":\"10139\",\"org\":\"JackHenry\",\"url\":\"https:\\/\\/directline2.netteller.com\"}'),(378,'La Banque Postale','813',1,'{\"fid\":\"0\",\"org\":\"0\",\"url\":\"https:\\/\\/ofx.videoposte.com\\/\"}'),(379,'Pennsylvania State Employees Credit Union','814',1,'{\"fid\":\"231381116\",\"org\":\"PENNSTATEEMPLOYEES\",\"url\":\"https:\\/\\/directconnect.psecu.com\\/ofxserver\\/ofxsrvr.dll\"}'),(380,'St. Mary\'s Credit Union','815',1,'{\"fid\":\"211384214\",\"org\":\"MSevenThirtySeven\",\"url\":\"https:\\/\\/ofx1.evault.ws\\/OFXServer\\/ofxsrvr.dll\"}'),(381,'Institution For Savings','816',1,'{\"fid\":\"59466\",\"org\":\"JackHenry\",\"url\":\"https:\\/\\/directline2.netteller.com\"}'),(382,'PNC Online Banking','817',1,'{\"fid\":\"4501\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/4501.ofxgp\"}'),(383,'PNC Banking Online','818',1,'{\"fid\":\"4501\",\"org\":\"ISC\",\"url\":\"https:\\/\\/www.oasis.cfree.com\\/4501.ofx\"}'),(384,'Central Bank Utah','820',1,'{\"fid\":\"124300327\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(385,'nuVision Financial FCU','821',1,'{\"fid\":\"322282399\",\"org\":\"DI\",\"url\":\"https:\\/\\/ofxdi.diginsite.com\\/cmr\\/cmr.ofx\"}'),(386,'Landings Credit Union','822',1,'{\"fid\":\"02114\",\"org\":\"JackHenry\",\"url\":\"https:\\/\\/directline.netteller.com\"}');
/*!40000 ALTER TABLE `banks` ENABLE KEYS */;
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
  `currency_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `address1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `address2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `postal_code` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `country_id` int(10) unsigned DEFAULT NULL,
  `work_phone` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `private_notes` text COLLATE utf8_unicode_ci,
  `balance` decimal(13,2) DEFAULT NULL,
  `paid_to_date` decimal(13,2) DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `website` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `industry_id` int(10) unsigned DEFAULT NULL,
  `size_id` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `payment_terms` int(11) DEFAULT NULL,
  `public_id` int(10) unsigned NOT NULL,
  `custom_value1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `custom_value2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `vat_number` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `id_number` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `language_id` int(10) unsigned DEFAULT NULL,
  `invoice_number_counter` int(11) DEFAULT '1',
  `quote_number_counter` int(11) DEFAULT '1',
  `public_notes` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `clients_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `clients_user_id_foreign` (`user_id`),
  KEY `clients_country_id_foreign` (`country_id`),
  KEY `clients_industry_id_foreign` (`industry_id`),
  KEY `clients_size_id_foreign` (`size_id`),
  KEY `clients_currency_id_foreign` (`currency_id`),
  KEY `clients_account_id_index` (`account_id`),
  KEY `clients_public_id_index` (`public_id`),
  KEY `clients_language_id_foreign` (`language_id`),
  CONSTRAINT `clients_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `clients_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `clients_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `clients_industry_id_foreign` FOREIGN KEY (`industry_id`) REFERENCES `industries` (`id`),
  CONSTRAINT `clients_language_id_foreign` FOREIGN KEY (`language_id`) REFERENCES `languages` (`id`),
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
-- Table structure for table `companies`
--

DROP TABLE IF EXISTS `companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `companies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `plan` enum('pro','enterprise','white_label') COLLATE utf8_unicode_ci DEFAULT NULL,
  `plan_term` enum('month','year') COLLATE utf8_unicode_ci DEFAULT NULL,
  `plan_started` date DEFAULT NULL,
  `plan_paid` date DEFAULT NULL,
  `plan_expires` date DEFAULT NULL,
  `payment_id` int(10) unsigned DEFAULT NULL,
  `trial_started` date DEFAULT NULL,
  `trial_plan` enum('pro','enterprise') COLLATE utf8_unicode_ci DEFAULT NULL,
  `pending_plan` enum('pro','enterprise','free') COLLATE utf8_unicode_ci DEFAULT NULL,
  `pending_term` enum('month','year') COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `plan_price` decimal(7,2) DEFAULT NULL,
  `pending_plan_price` decimal(7,2) DEFAULT NULL,
  `num_users` smallint(6) NOT NULL DEFAULT '1',
  `pending_num_users` smallint(6) NOT NULL DEFAULT '1',
  `utm_source` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `utm_medium` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `utm_campaign` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `utm_term` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `utm_content` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `discount` double(8,2) NOT NULL,
  `discount_expires` date DEFAULT NULL,
  `promo_expires` date DEFAULT NULL,
  `bluevine_status` enum('ignored','signed_up') COLLATE utf8_unicode_ci DEFAULT NULL,
  `referral_code` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `companies_payment_id_foreign` (`payment_id`),
  CONSTRAINT `companies_payment_id_foreign` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `companies`
--

LOCK TABLES `companies` WRITE;
/*!40000 ALTER TABLE `companies` DISABLE KEYS */;
/*!40000 ALTER TABLE `companies` ENABLE KEYS */;
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `send_invoice` tinyint(1) NOT NULL DEFAULT '0',
  `first_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `last_login` timestamp NULL DEFAULT NULL,
  `public_id` int(10) unsigned DEFAULT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `confirmation_code` tinyint(1) DEFAULT NULL,
  `remember_token` tinyint(1) DEFAULT NULL,
  `contact_key` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `bot_user_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `custom_value1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `custom_value2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contacts_account_id_public_id_unique` (`account_id`,`public_id`),
  UNIQUE KEY `contacts_contact_key_unique` (`contact_key`),
  KEY `contacts_user_id_foreign` (`user_id`),
  KEY `contacts_client_id_index` (`client_id`),
  CONSTRAINT `contacts_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contacts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
  `swap_postal_code` tinyint(1) NOT NULL DEFAULT '0',
  `swap_currency_symbol` tinyint(1) NOT NULL DEFAULT '0',
  `thousand_separator` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `decimal_separator` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=895 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `countries`
--

LOCK TABLES `countries` WRITE;
/*!40000 ALTER TABLE `countries` DISABLE KEYS */;
INSERT INTO `countries` VALUES (4,'Kabul','Afghan','004','afghani','AFN','pul','Islamic Republic of Afghanistan','AF','AFG','Afghanistan','142','034',0,0,0,NULL,NULL),(8,'Tirana','Albanian','008','lek','ALL','(qindar (pl. qindarka))','Republic of Albania','AL','ALB','Albania','150','039',0,0,0,NULL,NULL),(10,'Antartica','of Antartica','010','','','','Antarctica','AQ','ATA','Antarctica','','',0,0,0,NULL,NULL),(12,'Algiers','Algerian','012','Algerian dinar','DZD','centime','People’s Democratic Republic of Algeria','DZ','DZA','Algeria','002','015',0,0,0,NULL,NULL),(16,'Pago Pago','American Samoan','016','US dollar','USD','cent','Territory of American','AS','ASM','American Samoa','009','061',0,0,0,NULL,NULL),(20,'Andorra la Vella','Andorran','020','euro','EUR','cent','Principality of Andorra','AD','AND','Andorra','150','039',0,0,0,NULL,NULL),(24,'Luanda','Angolan','024','kwanza','AOA','cêntimo','Republic of Angola','AO','AGO','Angola','002','017',0,0,0,NULL,NULL),(28,'St John’s','of Antigua and Barbuda','028','East Caribbean dollar','XCD','cent','Antigua and Barbuda','AG','ATG','Antigua and Barbuda','019','029',0,0,0,NULL,NULL),(31,'Baku','Azerbaijani','031','Azerbaijani manat','AZN','kepik (inv.)','Republic of Azerbaijan','AZ','AZE','Azerbaijan','142','145',0,0,0,NULL,NULL),(32,'Buenos Aires','Argentinian','032','Argentine peso','ARS','centavo','Argentine Republic','AR','ARG','Argentina','019','005',0,1,0,NULL,NULL),(36,'Canberra','Australian','036','Australian dollar','AUD','cent','Commonwealth of Australia','AU','AUS','Australia','009','053',0,0,0,NULL,NULL),(40,'Vienna','Austrian','040','euro','EUR','cent','Republic of Austria','AT','AUT','Austria','150','155',1,1,1,NULL,NULL),(44,'Nassau','Bahamian','044','Bahamian dollar','BSD','cent','Commonwealth of the Bahamas','BS','BHS','Bahamas','019','029',0,0,0,NULL,NULL),(48,'Manama','Bahraini','048','Bahraini dinar','BHD','fils (inv.)','Kingdom of Bahrain','BH','BHR','Bahrain','142','145',0,0,0,NULL,NULL),(50,'Dhaka','Bangladeshi','050','taka (inv.)','BDT','poisha (inv.)','People’s Republic of Bangladesh','BD','BGD','Bangladesh','142','034',0,0,0,NULL,NULL),(51,'Yerevan','Armenian','051','dram (inv.)','AMD','luma','Republic of Armenia','AM','ARM','Armenia','142','145',0,0,0,NULL,NULL),(52,'Bridgetown','Barbadian','052','Barbados dollar','BBD','cent','Barbados','BB','BRB','Barbados','019','029',0,0,0,NULL,NULL),(56,'Brussels','Belgian','056','euro','EUR','cent','Kingdom of Belgium','BE','BEL','Belgium','150','155',1,1,0,NULL,NULL),(60,'Hamilton','Bermudian','060','Bermuda dollar','BMD','cent','Bermuda','BM','BMU','Bermuda','019','021',0,0,0,NULL,NULL),(64,'Thimphu','Bhutanese','064','ngultrum (inv.)','BTN','chhetrum (inv.)','Kingdom of Bhutan','BT','BTN','Bhutan','142','034',0,0,0,NULL,NULL),(68,'Sucre (BO1)','Bolivian','068','boliviano','BOB','centavo','Plurinational State of Bolivia','BO','BOL','Bolivia, Plurinational State of','019','005',0,0,0,NULL,NULL),(70,'Sarajevo','of Bosnia and Herzegovina','070','convertible mark','BAM','fening','Bosnia and Herzegovina','BA','BIH','Bosnia and Herzegovina','150','039',0,0,0,NULL,NULL),(72,'Gaborone','Botswanan','072','pula (inv.)','BWP','thebe (inv.)','Republic of Botswana','BW','BWA','Botswana','002','018',0,0,0,NULL,NULL),(74,'Bouvet island','of Bouvet island','074','','','','Bouvet Island','BV','BVT','Bouvet Island','','',0,0,0,NULL,NULL),(76,'Brasilia','Brazilian','076','real (pl. reais)','BRL','centavo','Federative Republic of Brazil','BR','BRA','Brazil','019','005',0,0,0,NULL,NULL),(84,'Belmopan','Belizean','084','Belize dollar','BZD','cent','Belize','BZ','BLZ','Belize','019','013',0,0,0,NULL,NULL),(86,'Diego Garcia','Changosian','086','US dollar','USD','cent','British Indian Ocean Territory','IO','IOT','British Indian Ocean Territory','','',0,0,0,NULL,NULL),(90,'Honiara','Solomon Islander','090','Solomon Islands dollar','SBD','cent','Solomon Islands','SB','SLB','Solomon Islands','009','054',0,0,0,NULL,NULL),(92,'Road Town','British Virgin Islander;','092','US dollar','USD','cent','British Virgin Islands','VG','VGB','Virgin Islands, British','019','029',0,0,0,NULL,NULL),(96,'Bandar Seri Begawan','Bruneian','096','Brunei dollar','BND','sen (inv.)','Brunei Darussalam','BN','BRN','Brunei Darussalam','142','035',0,0,0,NULL,NULL),(100,'Sofia','Bulgarian','100','lev (pl. leva)','BGN','stotinka','Republic of Bulgaria','BG','BGR','Bulgaria','150','151',1,0,1,NULL,NULL),(104,'Yangon','Burmese','104','kyat','MMK','pya','Union of Myanmar/','MM','MMR','Myanmar','142','035',0,0,0,NULL,NULL),(108,'Bujumbura','Burundian','108','Burundi franc','BIF','centime','Republic of Burundi','BI','BDI','Burundi','002','014',0,0,0,NULL,NULL),(112,'Minsk','Belarusian','112','Belarusian rouble','BYR','kopek','Republic of Belarus','BY','BLR','Belarus','150','151',0,0,0,NULL,NULL),(116,'Phnom Penh','Cambodian','116','riel','KHR','sen (inv.)','Kingdom of Cambodia','KH','KHM','Cambodia','142','035',0,0,0,NULL,NULL),(120,'Yaoundé','Cameroonian','120','CFA franc (BEAC)','XAF','centime','Republic of Cameroon','CM','CMR','Cameroon','002','017',0,0,0,NULL,NULL),(124,'Ottawa','Canadian','124','Canadian dollar','CAD','cent','Canada','CA','CAN','Canada','019','021',0,0,0,NULL,NULL),(132,'Praia','Cape Verdean','132','Cape Verde escudo','CVE','centavo','Republic of Cape Verde','CV','CPV','Cape Verde','002','011',0,0,0,NULL,NULL),(136,'George Town','Caymanian','136','Cayman Islands dollar','KYD','cent','Cayman Islands','KY','CYM','Cayman Islands','019','029',0,0,0,NULL,NULL),(140,'Bangui','Central African','140','CFA franc (BEAC)','XAF','centime','Central African Republic','CF','CAF','Central African Republic','002','017',0,0,0,NULL,NULL),(144,'Colombo','Sri Lankan','144','Sri Lankan rupee','LKR','cent','Democratic Socialist Republic of Sri Lanka','LK','LKA','Sri Lanka','142','034',0,0,0,NULL,NULL),(148,'N’Djamena','Chadian','148','CFA franc (BEAC)','XAF','centime','Republic of Chad','TD','TCD','Chad','002','017',0,0,0,NULL,NULL),(152,'Santiago','Chilean','152','Chilean peso','CLP','centavo','Republic of Chile','CL','CHL','Chile','019','005',0,0,0,NULL,NULL),(156,'Beijing','Chinese','156','renminbi-yuan (inv.)','CNY','jiao (10)','People’s Republic of China','CN','CHN','China','142','030',0,0,0,NULL,NULL),(158,'Taipei','Taiwanese','158','new Taiwan dollar','TWD','fen (inv.)','Republic of China, Taiwan (TW1)','TW','TWN','Taiwan, Province of China','142','030',0,0,0,NULL,NULL),(162,'Flying Fish Cove','Christmas Islander','162','Australian dollar','AUD','cent','Christmas Island Territory','CX','CXR','Christmas Island','','',0,0,0,NULL,NULL),(166,'Bantam','Cocos Islander','166','Australian dollar','AUD','cent','Territory of Cocos (Keeling) Islands','CC','CCK','Cocos (Keeling) Islands','','',0,0,0,NULL,NULL),(170,'Santa Fe de Bogotá','Colombian','170','Colombian peso','COP','centavo','Republic of Colombia','CO','COL','Colombia','019','005',0,0,0,NULL,NULL),(174,'Moroni','Comorian','174','Comorian franc','KMF','','Union of the Comoros','KM','COM','Comoros','002','014',0,0,0,NULL,NULL),(175,'Mamoudzou','Mahorais','175','euro','EUR','cent','Departmental Collectivity of Mayotte','YT','MYT','Mayotte','002','014',0,0,0,NULL,NULL),(178,'Brazzaville','Congolese','178','CFA franc (BEAC)','XAF','centime','Republic of the Congo','CG','COG','Congo','002','017',0,0,0,NULL,NULL),(180,'Kinshasa','Congolese','180','Congolese franc','CDF','centime','Democratic Republic of the Congo','CD','COD','Congo, the Democratic Republic of the','002','017',0,0,0,NULL,NULL),(184,'Avarua','Cook Islander','184','New Zealand dollar','NZD','cent','Cook Islands','CK','COK','Cook Islands','009','061',0,0,0,NULL,NULL),(188,'San José','Costa Rican','188','Costa Rican colón (pl. colones)','CRC','céntimo','Republic of Costa Rica','CR','CRI','Costa Rica','019','013',0,0,0,NULL,NULL),(191,'Zagreb','Croatian','191','kuna (inv.)','HRK','lipa (inv.)','Republic of Croatia','HR','HRV','Croatia','150','039',1,0,1,NULL,NULL),(192,'Havana','Cuban','192','Cuban peso','CUP','centavo','Republic of Cuba','CU','CUB','Cuba','019','029',0,0,0,NULL,NULL),(196,'Nicosia','Cypriot','196','euro','EUR','cent','Republic of Cyprus','CY','CYP','Cyprus','142','145',1,0,0,NULL,NULL),(203,'Prague','Czech','203','Czech koruna (pl. koruny)','CZK','halér','Czech Republic','CZ','CZE','Czech Republic','150','151',1,0,1,NULL,NULL),(204,'Porto Novo (BJ1)','Beninese','204','CFA franc (BCEAO)','XOF','centime','Republic of Benin','BJ','BEN','Benin','002','011',0,0,0,NULL,NULL),(208,'Copenhagen','Danish','208','Danish krone','DKK','øre (inv.)','Kingdom of Denmark','DK','DNK','Denmark','150','154',1,1,0,NULL,NULL),(212,'Roseau','Dominican','212','East Caribbean dollar','XCD','cent','Commonwealth of Dominica','DM','DMA','Dominica','019','029',0,0,0,NULL,NULL),(214,'Santo Domingo','Dominican','214','Dominican peso','DOP','centavo','Dominican Republic','DO','DOM','Dominican Republic','019','029',0,0,0,NULL,NULL),(218,'Quito','Ecuadorian','218','US dollar','USD','cent','Republic of Ecuador','EC','ECU','Ecuador','019','005',0,0,0,NULL,NULL),(222,'San Salvador','Salvadoran','222','Salvadorian colón (pl. colones)','SVC','centavo','Republic of El Salvador','SV','SLV','El Salvador','019','013',0,0,0,NULL,NULL),(226,'Malabo','Equatorial Guinean','226','CFA franc (BEAC)','XAF','centime','Republic of Equatorial Guinea','GQ','GNQ','Equatorial Guinea','002','017',0,0,0,NULL,NULL),(231,'Addis Ababa','Ethiopian','231','birr (inv.)','ETB','cent','Federal Democratic Republic of Ethiopia','ET','ETH','Ethiopia','002','014',0,0,0,NULL,NULL),(232,'Asmara','Eritrean','232','nakfa','ERN','cent','State of Eritrea','ER','ERI','Eritrea','002','014',0,0,0,NULL,NULL),(233,'Tallinn','Estonian','233','euro','EUR','cent','Republic of Estonia','EE','EST','Estonia','150','154',1,0,1,NULL,NULL),(234,'Tórshavn','Faeroese','234','Danish krone','DKK','øre (inv.)','Faeroe Islands','FO','FRO','Faroe Islands','150','154',0,0,0,NULL,NULL),(238,'Stanley','Falkland Islander','238','Falkland Islands pound','FKP','new penny','Falkland Islands','FK','FLK','Falkland Islands (Malvinas)','019','005',0,0,0,NULL,NULL),(239,'King Edward Point (Grytviken)','of South Georgia and the South Sandwich Islands','239','','','','South Georgia and the South Sandwich Islands','GS','SGS','South Georgia and the South Sandwich Islands','','',0,0,0,NULL,NULL),(242,'Suva','Fijian','242','Fiji dollar','FJD','cent','Republic of Fiji','FJ','FJI','Fiji','009','054',0,0,0,NULL,NULL),(246,'Helsinki','Finnish','246','euro','EUR','cent','Republic of Finland','FI','FIN','Finland','150','154',1,1,1,NULL,NULL),(248,'Mariehamn','Åland Islander','248','euro','EUR','cent','Åland Islands','AX','ALA','Åland Islands','150','154',0,0,0,NULL,NULL),(250,'Paris','French','250','euro','EUR','cent','French Republic','FR','FRA','France','150','155',1,1,1,NULL,NULL),(254,'Cayenne','Guianese','254','euro','EUR','cent','French Guiana','GF','GUF','French Guiana','019','005',0,0,0,NULL,NULL),(258,'Papeete','Polynesian','258','CFP franc','XPF','centime','French Polynesia','PF','PYF','French Polynesia','009','061',0,0,0,NULL,NULL),(260,'Port-aux-Francais','of French Southern and Antarctic Lands','260','euro','EUR','cent','French Southern and Antarctic Lands','TF','ATF','French Southern Territories','','',0,0,0,NULL,NULL),(262,'Djibouti','Djiboutian','262','Djibouti franc','DJF','','Republic of Djibouti','DJ','DJI','Djibouti','002','014',0,0,0,NULL,NULL),(266,'Libreville','Gabonese','266','CFA franc (BEAC)','XAF','centime','Gabonese Republic','GA','GAB','Gabon','002','017',0,0,0,NULL,NULL),(268,'Tbilisi','Georgian','268','lari','GEL','tetri (inv.)','Georgia','GE','GEO','Georgia','142','145',0,0,0,NULL,NULL),(270,'Banjul','Gambian','270','dalasi (inv.)','GMD','butut','Republic of the Gambia','GM','GMB','Gambia','002','011',0,0,0,NULL,NULL),(275,NULL,'Palestinian','275',NULL,NULL,NULL,NULL,'PS','PSE','Palestinian Territory, Occupied','142','145',0,0,0,NULL,NULL),(276,'Berlin','German','276','euro','EUR','cent','Federal Republic of Germany','DE','DEU','Germany','150','155',1,1,1,NULL,NULL),(288,'Accra','Ghanaian','288','Ghana cedi','GHS','pesewa','Republic of Ghana','GH','GHA','Ghana','002','011',0,0,0,NULL,NULL),(292,'Gibraltar','Gibraltarian','292','Gibraltar pound','GIP','penny','Gibraltar','GI','GIB','Gibraltar','150','039',0,0,0,NULL,NULL),(296,'Tarawa','Kiribatian','296','Australian dollar','AUD','cent','Republic of Kiribati','KI','KIR','Kiribati','009','057',0,0,0,NULL,NULL),(300,'Athens','Greek','300','euro','EUR','cent','Hellenic Republic','GR','GRC','Greece','150','039',1,0,1,NULL,NULL),(304,'Nuuk','Greenlander','304','Danish krone','DKK','øre (inv.)','Greenland','GL','GRL','Greenland','019','021',0,1,0,NULL,NULL),(308,'St George’s','Grenadian','308','East Caribbean dollar','XCD','cent','Grenada','GD','GRD','Grenada','019','029',0,0,0,NULL,NULL),(312,'Basse Terre','Guadeloupean','312','euro','EUR ','cent','Guadeloupe','GP','GLP','Guadeloupe','019','029',0,0,0,NULL,NULL),(316,'Agaña (Hagåtña)','Guamanian','316','US dollar','USD','cent','Territory of Guam','GU','GUM','Guam','009','057',0,0,0,NULL,NULL),(320,'Guatemala City','Guatemalan','320','quetzal (pl. quetzales)','GTQ','centavo','Republic of Guatemala','GT','GTM','Guatemala','019','013',0,0,0,NULL,NULL),(324,'Conakry','Guinean','324','Guinean franc','GNF','','Republic of Guinea','GN','GIN','Guinea','002','011',0,0,0,NULL,NULL),(328,'Georgetown','Guyanese','328','Guyana dollar','GYD','cent','Cooperative Republic of Guyana','GY','GUY','Guyana','019','005',0,0,0,NULL,NULL),(332,'Port-au-Prince','Haitian','332','gourde','HTG','centime','Republic of Haiti','HT','HTI','Haiti','019','029',0,0,0,NULL,NULL),(334,'Territory of Heard Island and McDonald Islands','of Territory of Heard Island and McDonald Islands','334','','','','Territory of Heard Island and McDonald Islands','HM','HMD','Heard Island and McDonald Islands','','',0,0,0,NULL,NULL),(336,'Vatican City','of the Holy See/of the Vatican','336','euro','EUR','cent','the Holy See/ Vatican City State','VA','VAT','Holy See (Vatican City State)','150','039',0,0,0,NULL,NULL),(340,'Tegucigalpa','Honduran','340','lempira','HNL','centavo','Republic of Honduras','HN','HND','Honduras','019','013',0,0,0,NULL,NULL),(344,'(HK3)','Hong Kong Chinese','344','Hong Kong dollar','HKD','cent','Hong Kong Special Administrative Region of the People’s Republic of China (HK2)','HK','HKG','Hong Kong','142','030',0,0,0,NULL,NULL),(348,'Budapest','Hungarian','348','forint (inv.)','HUF','(fillér (inv.))','Republic of Hungary','HU','HUN','Hungary','150','151',1,0,1,NULL,NULL),(352,'Reykjavik','Icelander','352','króna (pl. krónur)','ISK','','Republic of Iceland','IS','ISL','Iceland','150','154',1,1,1,NULL,NULL),(356,'New Delhi','Indian','356','Indian rupee','INR','paisa','Republic of India','IN','IND','India','142','034',0,0,0,NULL,NULL),(360,'Jakarta','Indonesian','360','Indonesian rupiah (inv.)','IDR','sen (inv.)','Republic of Indonesia','ID','IDN','Indonesia','142','035',0,0,0,NULL,NULL),(364,'Tehran','Iranian','364','Iranian rial','IRR','(dinar) (IR1)','Islamic Republic of Iran','IR','IRN','Iran, Islamic Republic of','142','034',0,0,0,NULL,NULL),(368,'Baghdad','Iraqi','368','Iraqi dinar','IQD','fils (inv.)','Republic of Iraq','IQ','IRQ','Iraq','142','145',0,0,0,NULL,NULL),(372,'Dublin','Irish','372','euro','EUR','cent','Ireland (IE1)','IE','IRL','Ireland','150','154',1,0,0,',','.'),(376,'(IL1)','Israeli','376','shekel','ILS','agora','State of Israel','IL','ISR','Israel','142','145',0,1,0,NULL,NULL),(380,'Rome','Italian','380','euro','EUR','cent','Italian Republic','IT','ITA','Italy','150','039',1,1,1,NULL,NULL),(384,'Yamoussoukro (CI1)','Ivorian','384','CFA franc (BCEAO)','XOF','centime','Republic of Côte d’Ivoire','CI','CIV','Côte d\'Ivoire','002','011',0,0,0,NULL,NULL),(388,'Kingston','Jamaican','388','Jamaica dollar','JMD','cent','Jamaica','JM','JAM','Jamaica','019','029',0,0,0,NULL,NULL),(392,'Tokyo','Japanese','392','yen (inv.)','JPY','(sen (inv.)) (JP1)','Japan','JP','JPN','Japan','142','030',0,1,1,NULL,NULL),(398,'Astana','Kazakh','398','tenge (inv.)','KZT','tiyn','Republic of Kazakhstan','KZ','KAZ','Kazakhstan','142','143',0,0,0,NULL,NULL),(400,'Amman','Jordanian','400','Jordanian dinar','JOD','100 qirsh','Hashemite Kingdom of Jordan','JO','JOR','Jordan','142','145',0,0,0,NULL,NULL),(404,'Nairobi','Kenyan','404','Kenyan shilling','KES','cent','Republic of Kenya','KE','KEN','Kenya','002','014',0,0,0,NULL,NULL),(408,'Pyongyang','North Korean','408','North Korean won (inv.)','KPW','chun (inv.)','Democratic People’s Republic of Korea','KP','PRK','Korea, Democratic People\'s Republic of','142','030',0,0,0,NULL,NULL),(410,'Seoul','South Korean','410','South Korean won (inv.)','KRW','(chun (inv.))','Republic of Korea','KR','KOR','Korea, Republic of','142','030',0,0,0,NULL,NULL),(414,'Kuwait City','Kuwaiti','414','Kuwaiti dinar','KWD','fils (inv.)','State of Kuwait','KW','KWT','Kuwait','142','145',0,0,0,NULL,NULL),(417,'Bishkek','Kyrgyz','417','som','KGS','tyiyn','Kyrgyz Republic','KG','KGZ','Kyrgyzstan','142','143',0,0,0,NULL,NULL),(418,'Vientiane','Lao','418','kip (inv.)','LAK','(at (inv.))','Lao People’s Democratic Republic','LA','LAO','Lao People\'s Democratic Republic','142','035',0,0,0,NULL,NULL),(422,'Beirut','Lebanese','422','Lebanese pound','LBP','(piastre)','Lebanese Republic','LB','LBN','Lebanon','142','145',0,0,0,NULL,NULL),(426,'Maseru','Basotho','426','loti (pl. maloti)','LSL','sente','Kingdom of Lesotho','LS','LSO','Lesotho','002','018',0,0,0,NULL,NULL),(428,'Riga','Latvian','428','euro','EUR','cent','Republic of Latvia','LV','LVA','Latvia','150','154',1,0,0,NULL,NULL),(430,'Monrovia','Liberian','430','Liberian dollar','LRD','cent','Republic of Liberia','LR','LBR','Liberia','002','011',0,0,0,NULL,NULL),(434,'Tripoli','Libyan','434','Libyan dinar','LYD','dirham','Socialist People’s Libyan Arab Jamahiriya','LY','LBY','Libya','002','015',0,0,0,NULL,NULL),(438,'Vaduz','Liechtensteiner','438','Swiss franc','CHF','centime','Principality of Liechtenstein','LI','LIE','Liechtenstein','150','155',1,0,0,NULL,NULL),(440,'Vilnius','Lithuanian','440','euro','EUR','cent','Republic of Lithuania','LT','LTU','Lithuania','150','154',1,0,1,NULL,NULL),(442,'Luxembourg','Luxembourger','442','euro','EUR','cent','Grand Duchy of Luxembourg','LU','LUX','Luxembourg','150','155',1,1,0,NULL,NULL),(446,'Macao (MO3)','Macanese','446','pataca','MOP','avo','Macao Special Administrative Region of the People’s Republic of China (MO2)','MO','MAC','Macao','142','030',0,0,0,NULL,NULL),(450,'Antananarivo','Malagasy','450','ariary','MGA','iraimbilanja (inv.)','Republic of Madagascar','MG','MDG','Madagascar','002','014',0,0,0,NULL,NULL),(454,'Lilongwe','Malawian','454','Malawian kwacha (inv.)','MWK','tambala (inv.)','Republic of Malawi','MW','MWI','Malawi','002','014',0,0,0,NULL,NULL),(458,'Kuala Lumpur (MY1)','Malaysian','458','ringgit (inv.)','MYR','sen (inv.)','Malaysia','MY','MYS','Malaysia','142','035',0,1,0,NULL,NULL),(462,'Malé','Maldivian','462','rufiyaa','MVR','laari (inv.)','Republic of Maldives','MV','MDV','Maldives','142','034',0,0,0,NULL,NULL),(466,'Bamako','Malian','466','CFA franc (BCEAO)','XOF','centime','Republic of Mali','ML','MLI','Mali','002','011',0,0,0,NULL,NULL),(470,'Valletta','Maltese','470','euro','EUR','cent','Republic of Malta','MT','MLT','Malta','150','039',1,0,0,NULL,NULL),(474,'Fort-de-France','Martinican','474','euro','EUR','cent','Martinique','MQ','MTQ','Martinique','019','029',0,0,0,NULL,NULL),(478,'Nouakchott','Mauritanian','478','ouguiya','MRO','khoum','Islamic Republic of Mauritania','MR','MRT','Mauritania','002','011',0,0,0,NULL,NULL),(480,'Port Louis','Mauritian','480','Mauritian rupee','MUR','cent','Republic of Mauritius','MU','MUS','Mauritius','002','014',0,0,0,NULL,NULL),(484,'Mexico City','Mexican','484','Mexican peso','MXN','centavo','United Mexican States','MX','MEX','Mexico','019','013',0,1,0,NULL,NULL),(492,'Monaco','Monegasque','492','euro','EUR','cent','Principality of Monaco','MC','MCO','Monaco','150','155',0,0,0,NULL,NULL),(496,'Ulan Bator','Mongolian','496','tugrik','MNT','möngö (inv.)','Mongolia','MN','MNG','Mongolia','142','030',0,0,0,NULL,NULL),(498,'Chisinau','Moldovan','498','Moldovan leu (pl. lei)','MDL','ban','Republic of Moldova','MD','MDA','Moldova, Republic of','150','151',0,0,0,NULL,NULL),(499,'Podgorica','Montenegrin','499','euro','EUR','cent','Montenegro','ME','MNE','Montenegro','150','039',0,0,0,NULL,NULL),(500,'Plymouth (MS2)','Montserratian','500','East Caribbean dollar','XCD','cent','Montserrat','MS','MSR','Montserrat','019','029',0,0,0,NULL,NULL),(504,'Rabat','Moroccan','504','Moroccan dirham','MAD','centime','Kingdom of Morocco','MA','MAR','Morocco','002','015',0,0,0,NULL,NULL),(508,'Maputo','Mozambican','508','metical','MZN','centavo','Republic of Mozambique','MZ','MOZ','Mozambique','002','014',0,0,0,NULL,NULL),(512,'Muscat','Omani','512','Omani rial','OMR','baiza','Sultanate of Oman','OM','OMN','Oman','142','145',0,0,0,NULL,NULL),(516,'Windhoek','Namibian','516','Namibian dollar','NAD','cent','Republic of Namibia','NA','NAM','Namibia','002','018',0,0,0,NULL,NULL),(520,'Yaren','Nauruan','520','Australian dollar','AUD','cent','Republic of Nauru','NR','NRU','Nauru','009','057',0,0,0,NULL,NULL),(524,'Kathmandu','Nepalese','524','Nepalese rupee','NPR','paisa (inv.)','Nepal','NP','NPL','Nepal','142','034',0,0,0,NULL,NULL),(528,'Amsterdam (NL2)','Dutch','528','euro','EUR','cent','Kingdom of the Netherlands','NL','NLD','Netherlands','150','155',1,1,0,NULL,NULL),(531,'Willemstad','Curaçaoan','531','Netherlands Antillean guilder (CW1)','ANG','cent','Curaçao','CW','CUW','Curaçao','019','029',0,0,0,NULL,NULL),(533,'Oranjestad','Aruban','533','Aruban guilder','AWG','cent','Aruba','AW','ABW','Aruba','019','029',0,0,0,NULL,NULL),(534,'Philipsburg','Sint Maartener','534','Netherlands Antillean guilder (SX1)','ANG','cent','Sint Maarten','SX','SXM','Sint Maarten (Dutch part)','019','029',0,0,0,NULL,NULL),(535,NULL,'of Bonaire, Sint Eustatius and Saba','535','US dollar','USD','cent',NULL,'BQ','BES','Bonaire, Sint Eustatius and Saba','019','029',0,0,0,NULL,NULL),(540,'Nouméa','New Caledonian','540','CFP franc','XPF','centime','New Caledonia','NC','NCL','New Caledonia','009','054',0,0,0,NULL,NULL),(548,'Port Vila','Vanuatuan','548','vatu (inv.)','VUV','','Republic of Vanuatu','VU','VUT','Vanuatu','009','054',0,0,0,NULL,NULL),(554,'Wellington','New Zealander','554','New Zealand dollar','NZD','cent','New Zealand','NZ','NZL','New Zealand','009','053',0,0,0,NULL,NULL),(558,'Managua','Nicaraguan','558','córdoba oro','NIO','centavo','Republic of Nicaragua','NI','NIC','Nicaragua','019','013',0,0,0,NULL,NULL),(562,'Niamey','Nigerien','562','CFA franc (BCEAO)','XOF','centime','Republic of Niger','NE','NER','Niger','002','011',0,0,0,NULL,NULL),(566,'Abuja','Nigerian','566','naira (inv.)','NGN','kobo (inv.)','Federal Republic of Nigeria','NG','NGA','Nigeria','002','011',0,0,0,NULL,NULL),(570,'Alofi','Niuean','570','New Zealand dollar','NZD','cent','Niue','NU','NIU','Niue','009','061',0,0,0,NULL,NULL),(574,'Kingston','Norfolk Islander','574','Australian dollar','AUD','cent','Territory of Norfolk Island','NF','NFK','Norfolk Island','009','053',0,0,0,NULL,NULL),(578,'Oslo','Norwegian','578','Norwegian krone (pl. kroner)','NOK','øre (inv.)','Kingdom of Norway','NO','NOR','Norway','150','154',1,0,0,NULL,NULL),(580,'Saipan','Northern Mariana Islander','580','US dollar','USD','cent','Commonwealth of the Northern Mariana Islands','MP','MNP','Northern Mariana Islands','009','057',0,0,0,NULL,NULL),(581,'United States Minor Outlying Islands','of United States Minor Outlying Islands','581','US dollar','USD','cent','United States Minor Outlying Islands','UM','UMI','United States Minor Outlying Islands','','',0,0,0,NULL,NULL),(583,'Palikir','Micronesian','583','US dollar','USD','cent','Federated States of Micronesia','FM','FSM','Micronesia, Federated States of','009','057',0,0,0,NULL,NULL),(584,'Majuro','Marshallese','584','US dollar','USD','cent','Republic of the Marshall Islands','MH','MHL','Marshall Islands','009','057',0,0,0,NULL,NULL),(585,'Melekeok','Palauan','585','US dollar','USD','cent','Republic of Palau','PW','PLW','Palau','009','057',0,0,0,NULL,NULL),(586,'Islamabad','Pakistani','586','Pakistani rupee','PKR','paisa','Islamic Republic of Pakistan','PK','PAK','Pakistan','142','034',0,0,0,NULL,NULL),(591,'Panama City','Panamanian','591','balboa','PAB','centésimo','Republic of Panama','PA','PAN','Panama','019','013',0,0,0,NULL,NULL),(598,'Port Moresby','Papua New Guinean','598','kina (inv.)','PGK','toea (inv.)','Independent State of Papua New Guinea','PG','PNG','Papua New Guinea','009','054',0,0,0,NULL,NULL),(600,'Asunción','Paraguayan','600','guaraní','PYG','céntimo','Republic of Paraguay','PY','PRY','Paraguay','019','005',0,0,0,NULL,NULL),(604,'Lima','Peruvian','604','new sol','PEN','céntimo','Republic of Peru','PE','PER','Peru','019','005',0,0,0,NULL,NULL),(608,'Manila','Filipino','608','Philippine peso','PHP','centavo','Republic of the Philippines','PH','PHL','Philippines','142','035',0,0,0,NULL,NULL),(612,'Adamstown','Pitcairner','612','New Zealand dollar','NZD','cent','Pitcairn Islands','PN','PCN','Pitcairn','009','061',0,0,0,NULL,NULL),(616,'Warsaw','Polish','616','zloty','PLN','grosz (pl. groszy)','Republic of Poland','PL','POL','Poland','150','151',1,1,1,NULL,NULL),(620,'Lisbon','Portuguese','620','euro','EUR','cent','Portuguese Republic','PT','PRT','Portugal','150','039',1,1,1,NULL,NULL),(624,'Bissau','Guinea-Bissau national','624','CFA franc (BCEAO)','XOF','centime','Republic of Guinea-Bissau','GW','GNB','Guinea-Bissau','002','011',0,0,0,NULL,NULL),(626,'Dili','East Timorese','626','US dollar','USD','cent','Democratic Republic of East Timor','TL','TLS','Timor-Leste','142','035',0,0,0,NULL,NULL),(630,'San Juan','Puerto Rican','630','US dollar','USD','cent','Commonwealth of Puerto Rico','PR','PRI','Puerto Rico','019','029',0,0,0,NULL,NULL),(634,'Doha','Qatari','634','Qatari riyal','QAR','dirham','State of Qatar','QA','QAT','Qatar','142','145',0,0,0,NULL,NULL),(638,'Saint-Denis','Reunionese','638','euro','EUR','cent','Réunion','RE','REU','Réunion','002','014',0,0,0,NULL,NULL),(642,'Bucharest','Romanian','642','Romanian leu (pl. lei)','RON','ban (pl. bani)','Romania','RO','ROU','Romania','150','151',1,0,1,NULL,NULL),(643,'Moscow','Russian','643','Russian rouble','RUB','kopek','Russian Federation','RU','RUS','Russian Federation','150','151',0,0,0,NULL,NULL),(646,'Kigali','Rwandan; Rwandese','646','Rwandese franc','RWF','centime','Republic of Rwanda','RW','RWA','Rwanda','002','014',0,0,0,NULL,NULL),(652,'Gustavia','of Saint Barthélemy','652','euro','EUR','cent','Collectivity of Saint Barthélemy','BL','BLM','Saint Barthélemy','019','029',0,0,0,NULL,NULL),(654,'Jamestown','Saint Helenian','654','Saint Helena pound','SHP','penny','Saint Helena, Ascension and Tristan da Cunha','SH','SHN','Saint Helena, Ascension and Tristan da Cunha','002','011',0,0,0,NULL,NULL),(659,'Basseterre','Kittsian; Nevisian','659','East Caribbean dollar','XCD','cent','Federation of Saint Kitts and Nevis','KN','KNA','Saint Kitts and Nevis','019','029',0,0,0,NULL,NULL),(660,'The Valley','Anguillan','660','East Caribbean dollar','XCD','cent','Anguilla','AI','AIA','Anguilla','019','029',0,0,0,NULL,NULL),(662,'Castries','Saint Lucian','662','East Caribbean dollar','XCD','cent','Saint Lucia','LC','LCA','Saint Lucia','019','029',0,0,0,NULL,NULL),(663,'Marigot','of Saint Martin','663','euro','EUR','cent','Collectivity of Saint Martin','MF','MAF','Saint Martin (French part)','019','029',0,0,0,NULL,NULL),(666,'Saint-Pierre','St-Pierrais; Miquelonnais','666','euro','EUR','cent','Territorial Collectivity of Saint Pierre and Miquelon','PM','SPM','Saint Pierre and Miquelon','019','021',0,0,0,NULL,NULL),(670,'Kingstown','Vincentian','670','East Caribbean dollar','XCD','cent','Saint Vincent and the Grenadines','VC','VCT','Saint Vincent and the Grenadines','019','029',0,0,0,NULL,NULL),(674,'San Marino','San Marinese','674','euro','EUR ','cent','Republic of San Marino','SM','SMR','San Marino','150','039',0,0,0,NULL,NULL),(678,'São Tomé','São Toméan','678','dobra','STD','centavo','Democratic Republic of São Tomé and Príncipe','ST','STP','Sao Tome and Principe','002','017',0,0,0,NULL,NULL),(682,'Riyadh','Saudi Arabian','682','riyal','SAR','halala','Kingdom of Saudi Arabia','SA','SAU','Saudi Arabia','142','145',0,0,0,NULL,NULL),(686,'Dakar','Senegalese','686','CFA franc (BCEAO)','XOF','centime','Republic of Senegal','SN','SEN','Senegal','002','011',0,0,0,NULL,NULL),(688,'Belgrade','Serb','688','Serbian dinar','RSD','para (inv.)','Republic of Serbia','RS','SRB','Serbia','150','039',0,0,0,NULL,NULL),(690,'Victoria','Seychellois','690','Seychelles rupee','SCR','cent','Republic of Seychelles','SC','SYC','Seychelles','002','014',0,0,0,NULL,NULL),(694,'Freetown','Sierra Leonean','694','leone','SLL','cent','Republic of Sierra Leone','SL','SLE','Sierra Leone','002','011',0,0,0,NULL,NULL),(702,'Singapore','Singaporean','702','Singapore dollar','SGD','cent','Republic of Singapore','SG','SGP','Singapore','142','035',0,0,0,NULL,NULL),(703,'Bratislava','Slovak','703','euro','EUR','cent','Slovak Republic','SK','SVK','Slovakia','150','151',1,0,1,NULL,NULL),(704,'Hanoi','Vietnamese','704','dong','VND','(10 hào','Socialist Republic of Vietnam','VN','VNM','Viet Nam','142','035',0,0,0,NULL,NULL),(705,'Ljubljana','Slovene','705','euro','EUR','cent','Republic of Slovenia','SI','SVN','Slovenia','150','039',1,0,1,NULL,NULL),(706,'Mogadishu','Somali','706','Somali shilling','SOS','cent','Somali Republic','SO','SOM','Somalia','002','014',0,0,0,NULL,NULL),(710,'Pretoria (ZA1)','South African','710','rand','ZAR','cent','Republic of South Africa','ZA','ZAF','South Africa','002','018',0,0,0,NULL,NULL),(716,'Harare','Zimbabwean','716','Zimbabwe dollar (ZW1)','ZWL','cent','Republic of Zimbabwe','ZW','ZWE','Zimbabwe','002','014',0,0,0,NULL,NULL),(724,'Madrid','Spaniard','724','euro','EUR','cent','Kingdom of Spain','ES','ESP','Spain','150','039',1,1,1,NULL,NULL),(728,'Juba','South Sudanese','728','South Sudanese pound','SSP','piaster','Republic of South Sudan','SS','SSD','South Sudan','002','015',0,0,0,NULL,NULL),(729,'Khartoum','Sudanese','729','Sudanese pound','SDG','piastre','Republic of the Sudan','SD','SDN','Sudan','002','015',0,0,0,NULL,NULL),(732,'Al aaiun','Sahrawi','732','Moroccan dirham','MAD','centime','Western Sahara','EH','ESH','Western Sahara','002','015',0,0,0,NULL,NULL),(740,'Paramaribo','Surinamese','740','Surinamese dollar','SRD','cent','Republic of Suriname','SR','SUR','Suriname','019','005',0,0,0,NULL,NULL),(744,'Longyearbyen','of Svalbard','744','Norwegian krone (pl. kroner)','NOK','øre (inv.)','Svalbard and Jan Mayen','SJ','SJM','Svalbard and Jan Mayen','150','154',0,0,0,NULL,NULL),(748,'Mbabane','Swazi','748','lilangeni','SZL','cent','Kingdom of Swaziland','SZ','SWZ','Swaziland','002','018',0,0,0,NULL,NULL),(752,'Stockholm','Swedish','752','krona (pl. kronor)','SEK','öre (inv.)','Kingdom of Sweden','SE','SWE','Sweden','150','154',1,1,1,NULL,NULL),(756,'Berne','Swiss','756','Swiss franc','CHF','centime','Swiss Confederation','CH','CHE','Switzerland','150','155',1,1,0,NULL,NULL),(760,'Damascus','Syrian','760','Syrian pound','SYP','piastre','Syrian Arab Republic','SY','SYR','Syrian Arab Republic','142','145',0,0,0,NULL,NULL),(762,'Dushanbe','Tajik','762','somoni','TJS','diram','Republic of Tajikistan','TJ','TJK','Tajikistan','142','143',0,0,0,NULL,NULL),(764,'Bangkok','Thai','764','baht (inv.)','THB','satang (inv.)','Kingdom of Thailand','TH','THA','Thailand','142','035',0,0,0,NULL,NULL),(768,'Lomé','Togolese','768','CFA franc (BCEAO)','XOF','centime','Togolese Republic','TG','TGO','Togo','002','011',0,0,0,NULL,NULL),(772,'(TK2)','Tokelauan','772','New Zealand dollar','NZD','cent','Tokelau','TK','TKL','Tokelau','009','061',0,0,0,NULL,NULL),(776,'Nuku’alofa','Tongan','776','pa’anga (inv.)','TOP','seniti (inv.)','Kingdom of Tonga','TO','TON','Tonga','009','061',0,0,0,NULL,NULL),(780,'Port of Spain','Trinidadian; Tobagonian','780','Trinidad and Tobago dollar','TTD','cent','Republic of Trinidad and Tobago','TT','TTO','Trinidad and Tobago','019','029',0,0,0,NULL,NULL),(784,'Abu Dhabi','Emirian','784','UAE dirham','AED','fils (inv.)','United Arab Emirates','AE','ARE','United Arab Emirates','142','145',0,0,0,NULL,NULL),(788,'Tunis','Tunisian','788','Tunisian dinar','TND','millime','Republic of Tunisia','TN','TUN','Tunisia','002','015',0,0,0,NULL,NULL),(792,'Ankara','Turk','792','Turkish lira (inv.)','TRY','kurus (inv.)','Republic of Turkey','TR','TUR','Turkey','142','145',0,0,0,NULL,NULL),(795,'Ashgabat','Turkmen','795','Turkmen manat (inv.)','TMT','tenge (inv.)','Turkmenistan','TM','TKM','Turkmenistan','142','143',0,0,0,NULL,NULL),(796,'Cockburn Town','Turks and Caicos Islander','796','US dollar','USD','cent','Turks and Caicos Islands','TC','TCA','Turks and Caicos Islands','019','029',0,0,0,NULL,NULL),(798,'Funafuti','Tuvaluan','798','Australian dollar','AUD','cent','Tuvalu','TV','TUV','Tuvalu','009','061',0,0,0,NULL,NULL),(800,'Kampala','Ugandan','800','Uganda shilling','UGX','cent','Republic of Uganda','UG','UGA','Uganda','002','014',0,0,0,NULL,NULL),(804,'Kiev','Ukrainian','804','hryvnia','UAH','kopiyka','Ukraine','UA','UKR','Ukraine','150','151',0,0,0,NULL,NULL),(807,'Skopje','of the former Yugoslav Republic of Macedonia','807','denar (pl. denars)','MKD','deni (inv.)','the former Yugoslav Republic of Macedonia','MK','MKD','Macedonia, the former Yugoslav Republic of','150','039',0,0,0,NULL,NULL),(818,'Cairo','Egyptian','818','Egyptian pound','EGP','piastre','Arab Republic of Egypt','EG','EGY','Egypt','002','015',0,0,0,NULL,NULL),(826,'London','British','826','pound sterling','GBP','penny (pl. pence)','United Kingdom of Great Britain and Northern Ireland','GB','GBR','United Kingdom','150','154',1,0,0,NULL,NULL),(831,'St Peter Port','of Guernsey','831','Guernsey pound (GG2)','GGP (GG2)','penny (pl. pence)','Bailiwick of Guernsey','GG','GGY','Guernsey','150','154',0,0,0,NULL,NULL),(832,'St Helier','of Jersey','832','Jersey pound (JE2)','JEP (JE2)','penny (pl. pence)','Bailiwick of Jersey','JE','JEY','Jersey','150','154',0,0,0,NULL,NULL),(833,'Douglas','Manxman; Manxwoman','833','Manx pound (IM2)','IMP (IM2)','penny (pl. pence)','Isle of Man','IM','IMN','Isle of Man','150','154',0,0,0,NULL,NULL),(834,'Dodoma (TZ1)','Tanzanian','834','Tanzanian shilling','TZS','cent','United Republic of Tanzania','TZ','TZA','Tanzania, United Republic of','002','014',0,0,0,NULL,NULL),(840,'Washington DC','American','840','US dollar','USD','cent','United States of America','US','USA','United States','019','021',0,0,0,',','.'),(850,'Charlotte Amalie','US Virgin Islander','850','US dollar','USD','cent','United States Virgin Islands','VI','VIR','Virgin Islands, U.S.','019','029',0,0,0,NULL,NULL),(854,'Ouagadougou','Burkinabe','854','CFA franc (BCEAO)','XOF','centime','Burkina Faso','BF','BFA','Burkina Faso','002','011',0,0,0,NULL,NULL),(858,'Montevideo','Uruguayan','858','Uruguayan peso','UYU','centésimo','Eastern Republic of Uruguay','UY','URY','Uruguay','019','005',0,1,0,NULL,NULL),(860,'Tashkent','Uzbek','860','sum (inv.)','UZS','tiyin (inv.)','Republic of Uzbekistan','UZ','UZB','Uzbekistan','142','143',0,0,0,NULL,NULL),(862,'Caracas','Venezuelan','862','bolívar fuerte (pl. bolívares fuertes)','VEF','céntimo','Bolivarian Republic of Venezuela','VE','VEN','Venezuela, Bolivarian Republic of','019','005',0,0,0,NULL,NULL),(876,'Mata-Utu','Wallisian; Futunan; Wallis and Futuna Islander','876','CFP franc','XPF','centime','Wallis and Futuna','WF','WLF','Wallis and Futuna','009','061',0,0,0,NULL,NULL),(882,'Apia','Samoan','882','tala (inv.)','WST','sene (inv.)','Independent State of Samoa','WS','WSM','Samoa','009','061',0,0,0,NULL,NULL),(887,'San’a','Yemenite','887','Yemeni rial','YER','fils (inv.)','Republic of Yemen','YE','YEM','Yemen','142','145',0,0,0,NULL,NULL),(894,'Lusaka','Zambian','894','Zambian kwacha (inv.)','ZMW','ngwee (inv.)','Republic of Zambia','ZM','ZMB','Zambia','002','014',0,0,0,NULL,NULL);
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `amount` decimal(13,2) NOT NULL,
  `balance` decimal(13,2) NOT NULL,
  `credit_date` date DEFAULT NULL,
  `credit_number` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `private_notes` text COLLATE utf8_unicode_ci NOT NULL,
  `public_id` int(10) unsigned NOT NULL,
  `public_notes` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `credits_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `credits_user_id_foreign` (`user_id`),
  KEY `credits_account_id_index` (`account_id`),
  KEY `credits_client_id_index` (`client_id`),
  KEY `credits_public_id_index` (`public_id`),
  CONSTRAINT `credits_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `credits_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `credits_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
  `swap_currency_symbol` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `currencies`
--

LOCK TABLES `currencies` WRITE;
/*!40000 ALTER TABLE `currencies` DISABLE KEYS */;
INSERT INTO `currencies` VALUES (1,'US Dollar','$','2',',','.','USD',0),(2,'British Pound','£','2',',','.','GBP',0),(3,'Euro','€','2','.',',','EUR',0),(4,'South African Rand','R','2','.',',','ZAR',0),(5,'Danish Krone','kr','2','.',',','DKK',1),(6,'Israeli Shekel','NIS ','2',',','.','ILS',0),(7,'Swedish Krona','kr','2','.',',','SEK',1),(8,'Kenyan Shilling','KSh ','2',',','.','KES',0),(9,'Canadian Dollar','C$','2',',','.','CAD',0),(10,'Philippine Peso','P ','2',',','.','PHP',0),(11,'Indian Rupee','Rs. ','2',',','.','INR',0),(12,'Australian Dollar','$','2',',','.','AUD',0),(13,'Singapore Dollar','','2',',','.','SGD',0),(14,'Norske Kroner','kr','2','.',',','NOK',1),(15,'New Zealand Dollar','$','2',',','.','NZD',0),(16,'Vietnamese Dong','','0','.',',','VND',0),(17,'Swiss Franc','','2','\'','.','CHF',0),(18,'Guatemalan Quetzal','Q','2',',','.','GTQ',0),(19,'Malaysian Ringgit','RM','2',',','.','MYR',0),(20,'Brazilian Real','R$','2','.',',','BRL',0),(21,'Thai Baht','','2',',','.','THB',0),(22,'Nigerian Naira','','2',',','.','NGN',0),(23,'Argentine Peso','$','2','.',',','ARS',0),(24,'Bangladeshi Taka','Tk','2',',','.','BDT',0),(25,'United Arab Emirates Dirham','DH ','2',',','.','AED',0),(26,'Hong Kong Dollar','','2',',','.','HKD',0),(27,'Indonesian Rupiah','Rp','2',',','.','IDR',0),(28,'Mexican Peso','$','2',',','.','MXN',0),(29,'Egyptian Pound','E£','2',',','.','EGP',0),(30,'Colombian Peso','$','2','.',',','COP',0),(31,'West African Franc','CFA ','2',',','.','XOF',0),(32,'Chinese Renminbi','RMB ','2',',','.','CNY',0),(33,'Rwandan Franc','RF ','2',',','.','RWF',0),(34,'Tanzanian Shilling','TSh ','2',',','.','TZS',0),(35,'Netherlands Antillean Guilder','','2','.',',','ANG',0),(36,'Trinidad and Tobago Dollar','TT$','2',',','.','TTD',0),(37,'East Caribbean Dollar','EC$','2',',','.','XCD',0),(38,'Ghanaian Cedi','','2',',','.','GHS',0),(39,'Bulgarian Lev','','2',' ','.','BGN',0),(40,'Aruban Florin','Afl. ','2',' ','.','AWG',0),(41,'Turkish Lira','TL ','2','.',',','TRY',0),(42,'Romanian New Leu','','2',',','.','RON',0),(43,'Croatian Kuna','kn','2','.',',','HRK',0),(44,'Saudi Riyal','','2',',','.','SAR',0),(45,'Japanese Yen','¥','0',',','.','JPY',0),(46,'Maldivian Rufiyaa','','2',',','.','MVR',0),(47,'Costa Rican Colón','','2',',','.','CRC',0),(48,'Pakistani Rupee','Rs ','0',',','.','PKR',0),(49,'Polish Zloty','zł','2',' ',',','PLN',1),(50,'Sri Lankan Rupee','LKR','2',',','.','LKR',1),(51,'Czech Koruna','Kč','2',' ',',','CZK',1),(52,'Uruguayan Peso','$','2','.',',','UYU',0),(53,'Namibian Dollar','$','2',',','.','NAD',0),(54,'Tunisian Dinar','','2',',','.','TND',0),(55,'Russian Ruble','','2',',','.','RUB',0),(56,'Mozambican Metical','MT','2','.',',','MZN',1),(57,'Omani Rial','','2',',','.','OMR',0),(58,'Ukrainian Hryvnia','','2',',','.','UAH',0),(59,'Macanese Pataca','MOP$','2',',','.','MOP',0),(60,'Taiwan New Dollar','NT$','2',',','.','TWD',0),(61,'Dominican Peso','RD$','2',',','.','DOP',0),(62,'Chilean Peso','$','2','.',',','CLP',0),(63,'Icelandic Króna','kr','2','.',',','ISK',1),(64,'Papua New Guinean Kina','K','2',',','.','PGK',0),(65,'Jordanian Dinar','','2',',','.','JOD',0);
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
  `format_moment` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `date_formats`
--

LOCK TABLES `date_formats` WRITE;
/*!40000 ALTER TABLE `date_formats` DISABLE KEYS */;
INSERT INTO `date_formats` VALUES (1,'d/M/Y','dd/M/yyyy','DD/MMM/YYYY'),(2,'d-M-Y','dd-M-yyyy','DD-MMM-YYYY'),(3,'d/F/Y','dd/MM/yyyy','DD/MMMM/YYYY'),(4,'d-F-Y','dd-MM-yyyy','DD-MMMM-YYYY'),(5,'M j, Y','M d, yyyy','MMM D, YYYY'),(6,'F j, Y','MM d, yyyy','MMMM D, YYYY'),(7,'D M j, Y','D MM d, yyyy','ddd MMM Do, YYYY'),(8,'Y-m-d','yyyy-mm-dd','YYYY-MM-DD'),(9,'d-m-Y','dd-mm-yyyy','DD-MM-YYYY'),(10,'m/d/Y','mm/dd/yyyy','MM/DD/YYYY'),(11,'d.m.Y','dd.mm.yyyy','D.MM.YYYY'),(12,'j. M. Y','d. M. yyyy','DD. MMM. YYYY'),(13,'j. F Y','d. MM yyyy','DD. MMMM YYYY');
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
  `format_moment` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `datetime_formats`
--

LOCK TABLES `datetime_formats` WRITE;
/*!40000 ALTER TABLE `datetime_formats` DISABLE KEYS */;
INSERT INTO `datetime_formats` VALUES (1,'d/M/Y g:i a','DD/MMM/YYYY h:mm:ss a'),(2,'d-M-Y g:i a','DD-MMM-YYYY h:mm:ss a'),(3,'d/F/Y g:i a','DD/MMMM/YYYY h:mm:ss a'),(4,'d-F-Y g:i a','DD-MMMM-YYYY h:mm:ss a'),(5,'M j, Y g:i a','MMM D, YYYY h:mm:ss a'),(6,'F j, Y g:i a','MMMM D, YYYY h:mm:ss a'),(7,'D M jS, Y g:i a','ddd MMM Do, YYYY h:mm:ss a'),(8,'Y-m-d g:i a','YYYY-MM-DD h:mm:ss a'),(9,'d-m-Y g:i a','DD-MM-YYYY h:mm:ss a'),(10,'m/d/Y g:i a','MM/DD/YYYY h:mm:ss a'),(11,'d.m.Y g:i a','D.MM.YYYY h:mm:ss a'),(12,'j. M. Y g:i a','DD. MMM. YYYY h:mm:ss a'),(13,'j. F Y g:i a','DD. MMMM YYYY h:mm:ss a');
/*!40000 ALTER TABLE `datetime_formats` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `db_servers`
--

DROP TABLE IF EXISTS `db_servers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `db_servers` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `db_servers`
--

LOCK TABLES `db_servers` WRITE;
/*!40000 ALTER TABLE `db_servers` DISABLE KEYS */;
INSERT INTO `db_servers` VALUES (1,'db-ninja-1'),(2,'db-ninja-2');
/*!40000 ALTER TABLE `db_servers` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `documents`
--

DROP TABLE IF EXISTS `documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `documents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `public_id` int(10) unsigned DEFAULT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `expense_id` int(10) unsigned DEFAULT NULL,
  `path` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `preview` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `disk` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `hash` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `size` int(10) unsigned NOT NULL,
  `width` int(10) unsigned DEFAULT NULL,
  `height` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `documents_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `documents_user_id_foreign` (`user_id`),
  KEY `documents_invoice_id_foreign` (`invoice_id`),
  KEY `documents_expense_id_foreign` (`expense_id`),
  CONSTRAINT `documents_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documents_expense_id_foreign` FOREIGN KEY (`expense_id`) REFERENCES `expenses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documents_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `documents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `documents`
--

LOCK TABLES `documents` WRITE;
/*!40000 ALTER TABLE `documents` DISABLE KEYS */;
/*!40000 ALTER TABLE `documents` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expense_categories`
--

DROP TABLE IF EXISTS `expense_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expense_categories` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `public_id` int(10) unsigned NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `expense_categories_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `expense_categories_account_id_index` (`account_id`),
  KEY `expense_categories_public_id_index` (`public_id`),
  KEY `expense_categories_user_id_foreign` (`user_id`),
  CONSTRAINT `expense_categories_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expense_categories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expense_categories`
--

LOCK TABLES `expense_categories` WRITE;
/*!40000 ALTER TABLE `expense_categories` DISABLE KEYS */;
/*!40000 ALTER TABLE `expense_categories` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `expenses`
--

DROP TABLE IF EXISTS `expenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `expenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `vendor_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `amount` decimal(13,2) NOT NULL,
  `exchange_rate` decimal(13,4) NOT NULL,
  `expense_date` date DEFAULT NULL,
  `private_notes` text COLLATE utf8_unicode_ci NOT NULL,
  `public_notes` text COLLATE utf8_unicode_ci NOT NULL,
  `invoice_currency_id` int(10) unsigned NOT NULL,
  `should_be_invoiced` tinyint(1) NOT NULL DEFAULT '1',
  `public_id` int(10) unsigned NOT NULL,
  `transaction_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `bank_id` int(10) unsigned DEFAULT NULL,
  `expense_currency_id` int(10) unsigned DEFAULT NULL,
  `expense_category_id` int(10) unsigned DEFAULT NULL,
  `tax_name1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tax_rate1` decimal(13,3) NOT NULL,
  `tax_name2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tax_rate2` decimal(13,3) NOT NULL,
  `payment_type_id` int(10) unsigned DEFAULT NULL,
  `payment_date` date DEFAULT NULL,
  `transaction_reference` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `invoice_documents` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `expenses_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `expenses_user_id_foreign` (`user_id`),
  KEY `expenses_account_id_index` (`account_id`),
  KEY `expenses_public_id_index` (`public_id`),
  KEY `expenses_expense_currency_id_index` (`expense_currency_id`),
  KEY `expenses_invoice_currency_id_foreign` (`invoice_currency_id`),
  KEY `expenses_expense_category_id_index` (`expense_category_id`),
  KEY `expenses_payment_type_id_foreign` (`payment_type_id`),
  CONSTRAINT `expenses_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_expense_category_id_foreign` FOREIGN KEY (`expense_category_id`) REFERENCES `expense_categories` (`id`) ON DELETE CASCADE,
  CONSTRAINT `expenses_expense_currency_id_foreign` FOREIGN KEY (`expense_currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `expenses_invoice_currency_id_foreign` FOREIGN KEY (`invoice_currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `expenses_payment_type_id_foreign` FOREIGN KEY (`payment_type_id`) REFERENCES `payment_types` (`id`),
  CONSTRAINT `expenses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `expenses`
--

LOCK TABLES `expenses` WRITE;
/*!40000 ALTER TABLE `expenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `expenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `connection` text COLLATE utf8_unicode_ci NOT NULL,
  `queue` text COLLATE utf8_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `failed_jobs`
--

LOCK TABLES `failed_jobs` WRITE;
/*!40000 ALTER TABLE `failed_jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `failed_jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `fonts`
--

DROP TABLE IF EXISTS `fonts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `fonts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `folder` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `css_stack` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `css_weight` smallint(6) NOT NULL DEFAULT '400',
  `google_font` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `normal` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `bold` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `italics` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `bolditalics` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `sort_order` int(10) unsigned NOT NULL DEFAULT '10000',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `fonts`
--

LOCK TABLES `fonts` WRITE;
/*!40000 ALTER TABLE `fonts` DISABLE KEYS */;
INSERT INTO `fonts` VALUES (1,'Roboto','roboto','\'Roboto\', Arial, Helvetica, sans-serif',400,'Roboto:400,700,900,100','Roboto-Regular.ttf','Roboto-Medium.ttf','Roboto-Italic.ttf','Roboto-Italic.ttf',100),(2,'Abril Fatface','abril_fatface','\'Abril Fatface\', Georgia, serif',400,'Abril+Fatface','AbrilFatface-Regular.ttf','AbrilFatface-Regular.ttf','AbrilFatface-Regular.ttf','AbrilFatface-Regular.ttf',200),(3,'Arvo','arvo','\'Arvo\', Georgia, serif',400,'Arvo:400,700','Arvo-Regular.ttf','Arvo-Bold.ttf','Arvo-Italic.ttf','Arvo-Italic.ttf',300),(4,'Josefin Sans','josefin_sans','\'Josefin Sans\', Arial, Helvetica, sans-serif',400,'Josefin Sans:400,700,900,100','JosefinSans-Regular.ttf','JosefinSans-Bold.ttf','JosefinSans-Italic.ttf','JosefinSans-Italic.ttf',400),(5,'Josefin Sans Light','josefin_sans_light','\'Josefin Sans\', Arial, Helvetica, sans-serif',300,'Josefin+Sans:300,700,900,100','JosefinSans-Light.ttf','JosefinSans-SemiBold.ttf','JosefinSans-LightItalic.ttf','JosefinSans-LightItalic.ttf',600),(6,'Josefin Slab','josefin_slab','\'Josefin Slab\', Arial, Helvetica, sans-serif',400,'Josefin Sans:400,700,900,100','JosefinSlab-Regular.ttf','JosefinSlab-Bold.ttf','JosefinSlab-Italic.ttf','JosefinSlab-Italic.ttf',700),(7,'Josefin Slab Light','josefin_slab_light','\'Josefin Slab\', Georgia, serif',300,'Josefin+Sans:400,700,900,100','JosefinSlab-Light.ttf','JosefinSlab-SemiBold.ttf','JosefinSlab-LightItalic.ttf','JosefinSlab-LightItalic.ttf',800),(8,'Open Sans','open_sans','\'Open Sans\', Arial, Helvetica, sans-serif',400,'Open+Sans:400,700,900,100','OpenSans-Regular.ttf','OpenSans-Semibold.ttf','OpenSans-Italic.ttf','OpenSans-Italic.ttf',900),(9,'Open Sans Light','open_sans_light','\'Open Sans\', Arial, Helvetica, sans-serif',300,'Open+Sans:300,700,900,100','OpenSans-Light.ttf','OpenSans-Regular.ttf','OpenSans-LightItalic.ttf','OpenSans-LightItalic.ttf',1000),(10,'PT Sans','pt_sans','\'PT Sans\', Arial, Helvetica, sans-serif',400,'PT+Sans:400,700,900,100','PTSans-Regular.ttf','PTSans-Bold.ttf','PTSans-Italic.ttf','PTSans-Italic.ttf',1100),(11,'PT Serif','pt_serif','\'PT Serif\', Georgia, serif',400,'PT+Serif:400,700,900,100','PTSerif-Regular.ttf','PTSerif-Bold.ttf','PTSerif-Italic.ttf','PTSerif-Italic.ttf',1200),(12,'Raleway','raleway','\'Raleway\', Arial, Helvetica, sans-serif',400,'Raleway:400,700,900,100','Raleway-Regular.ttf','Raleway-Medium.ttf','Raleway-Italic.ttf','Raleway-Italic.ttf',1300),(13,'Raleway Light','raleway_light','\'Raleway\', Arial, Helvetica, sans-serif',300,'Raleway:300,700,900,100','Raleway-Light.ttf','Raleway-Medium.ttf','Raleway-LightItalic.ttf','Raleway-LightItalic.ttf',1400),(14,'Titillium','titillium','\'Titillium Web\', Arial, Helvetica, sans-serif',400,'Titillium+Web:400,700,900,100','TitilliumWeb-Regular.ttf','TitilliumWeb-Bold.ttf','TitilliumWeb-Italic.ttf','TitilliumWeb-Italic.ttf',1500),(15,'Titillium Light','titillium_light','\'Titillium Web\', Arial, Helvetica, sans-serif',300,'Titillium+Web:300,700,900,100','TitilliumWeb-Light.ttf','TitilliumWeb-SemiBold.ttf','TitilliumWeb-LightItalic.ttf','TitilliumWeb-LightItalic.ttf',1600),(16,'Ubuntu','ubuntu','\'Ubuntu\', Arial, Helvetica, sans-serif',400,'Ubuntu:400,700,900,100','Ubuntu-Regular.ttf','Ubuntu-Bold.ttf','Ubuntu-Italic.ttf','Ubuntu-Italic.ttf',1700),(17,'Ubuntu Light','ubuntu_light','\'Ubuntu\', Arial, Helvetica, sans-serif',300,'Ubuntu:200,700,900,100','Ubuntu-Light.ttf','Ubuntu-Medium.ttf','Ubuntu-LightItalic.ttf','Ubuntu-LightItalic.ttf',1800),(18,'UKai - Chinese','ukai','',400,'','UKai.ttf','UKai.ttf','UKai.ttf','UKai.ttf',1800),(19,'GenshinGothic P - Japanese','gensha_gothic_p','',400,'','GenShinGothic-P-Regular.ttf','GenShinGothic-P-Regular.ttf','GenShinGothic-P-Regular.ttf','GenShinGothic-P-Regular.ttf',1800),(20,'GenshinGothic - Japanese','gensha_gothic','',400,'','GenShinGothic-Regular.ttf','GenShinGothic-Regular.ttf','GenShinGothic-Regular.ttf','GenShinGothic-Regular.ttf',1800);
/*!40000 ALTER TABLE `fonts` ENABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `frequencies`
--

LOCK TABLES `frequencies` WRITE;
/*!40000 ALTER TABLE `frequencies` DISABLE KEYS */;
INSERT INTO `frequencies` VALUES (1,'Weekly'),(2,'Two weeks'),(3,'Four weeks'),(4,'Monthly'),(5,'Two months'),(6,'Three months'),(7,'Six months'),(8,'Annually');
/*!40000 ALTER TABLE `frequencies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gateway_types`
--

DROP TABLE IF EXISTS `gateway_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gateway_types` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `alias` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gateway_types`
--

LOCK TABLES `gateway_types` WRITE;
/*!40000 ALTER TABLE `gateway_types` DISABLE KEYS */;
INSERT INTO `gateway_types` VALUES (1,'credit_card','Credit Card'),(2,'bank_transfer','Bank Transfer'),(3,'paypal','PayPal'),(4,'bitcoin','Bitcoin'),(5,'dwolla','Dwolla'),(6,'custom','Custom');
/*!40000 ALTER TABLE `gateway_types` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `gateways`
--

DROP TABLE IF EXISTS `gateways`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gateways` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `provider` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `payment_library_id` int(10) unsigned NOT NULL DEFAULT '1',
  `sort_order` int(10) unsigned NOT NULL DEFAULT '10000',
  `recommended` tinyint(1) NOT NULL DEFAULT '0',
  `site_url` varchar(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_offsite` tinyint(1) NOT NULL,
  `is_secure` tinyint(1) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `gateways_payment_library_id_foreign` (`payment_library_id`),
  CONSTRAINT `gateways_payment_library_id_foreign` FOREIGN KEY (`payment_library_id`) REFERENCES `payment_libraries` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=63 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `gateways`
--

LOCK TABLES `gateways` WRITE;
/*!40000 ALTER TABLE `gateways` DISABLE KEYS */;
INSERT INTO `gateways` VALUES (1,'2017-06-15 04:43:57','2017-06-15 04:43:57','Authorize.Net AIM','AuthorizeNet_AIM',1,1,4,0,NULL,0,0),(2,'2017-06-15 04:43:57','2017-06-15 04:43:57','Authorize.Net SIM','AuthorizeNet_SIM',1,2,10000,0,NULL,0,0),(3,'2017-06-15 04:43:57','2017-06-15 04:43:57','CardSave','CardSave',1,1,10000,0,NULL,0,0),(4,'2017-06-15 04:43:57','2017-06-15 04:43:57','Eway Rapid','Eway_RapidShared',1,1,10000,0,NULL,1,0),(5,'2017-06-15 04:43:57','2017-06-15 04:43:57','FirstData Connect','FirstData_Connect',1,1,10000,0,NULL,0,0),(6,'2017-06-15 04:43:57','2017-06-15 04:43:57','GoCardless','GoCardless',1,1,10000,0,NULL,1,0),(7,'2017-06-15 04:43:57','2017-06-15 04:43:57','Migs ThreeParty','Migs_ThreeParty',1,1,10000,0,NULL,0,0),(8,'2017-06-15 04:43:57','2017-06-15 04:43:57','Migs TwoParty','Migs_TwoParty',1,1,10000,0,NULL,0,0),(9,'2017-06-15 04:43:57','2017-06-15 04:43:57','Mollie','Mollie',1,1,7,0,NULL,1,0),(10,'2017-06-15 04:43:57','2017-06-15 04:43:57','MultiSafepay','MultiSafepay',1,1,10000,0,NULL,0,0),(11,'2017-06-15 04:43:57','2017-06-15 04:43:57','Netaxept','Netaxept',1,1,10000,0,NULL,0,0),(12,'2017-06-15 04:43:57','2017-06-15 04:43:57','NetBanx','NetBanx',1,1,10000,0,NULL,0,0),(13,'2017-06-15 04:43:57','2017-06-15 04:43:57','PayFast','PayFast',1,1,10000,0,NULL,1,0),(14,'2017-06-15 04:43:57','2017-06-15 04:43:57','Payflow Pro','Payflow_Pro',1,1,10000,0,NULL,0,0),(15,'2017-06-15 04:43:57','2017-06-15 04:43:57','PaymentExpress PxPay','PaymentExpress_PxPay',1,1,10000,0,NULL,0,0),(16,'2017-06-15 04:43:57','2017-06-15 04:43:57','PaymentExpress PxPost','PaymentExpress_PxPost',1,1,10000,0,NULL,0,0),(17,'2017-06-15 04:43:57','2017-06-15 04:43:57','PayPal Express','PayPal_Express',1,1,3,0,NULL,1,0),(18,'2017-06-15 04:43:57','2017-06-15 04:43:57','PayPal Pro','PayPal_Pro',1,1,10000,0,NULL,0,0),(19,'2017-06-15 04:43:57','2017-06-15 04:43:57','Pin','Pin',1,1,10000,0,NULL,0,0),(20,'2017-06-15 04:43:57','2017-06-15 04:43:57','SagePay Direct','SagePay_Direct',1,1,10000,0,NULL,0,0),(21,'2017-06-15 04:43:57','2017-06-15 04:43:57','SagePay Server','SagePay_Server',1,1,10000,0,NULL,0,0),(22,'2017-06-15 04:43:57','2017-06-15 04:43:57','SecurePay DirectPost','SecurePay_DirectPost',1,1,10000,0,NULL,0,0),(23,'2017-06-15 04:43:58','2017-06-15 04:43:58','Stripe','Stripe',1,1,1,0,NULL,0,0),(24,'2017-06-15 04:43:58','2017-06-15 04:43:58','TargetPay Direct eBanking','TargetPay_Directebanking',1,1,10000,0,NULL,0,0),(25,'2017-06-15 04:43:58','2017-06-15 04:43:58','TargetPay Ideal','TargetPay_Ideal',1,1,10000,0,NULL,0,0),(26,'2017-06-15 04:43:58','2017-06-15 04:43:58','TargetPay Mr Cash','TargetPay_Mrcash',1,1,10000,0,NULL,0,0),(27,'2017-06-15 04:43:58','2017-06-15 04:43:58','TwoCheckout','TwoCheckout',1,1,10000,0,NULL,1,0),(28,'2017-06-15 04:43:58','2017-06-15 04:43:58','WorldPay','WorldPay',1,1,10000,0,NULL,0,0),(29,'2017-06-15 04:43:58','2017-06-15 04:43:58','BeanStream','BeanStream',1,2,10000,0,NULL,0,0),(30,'2017-06-15 04:43:58','2017-06-15 04:43:58','Psigate','Psigate',1,2,10000,0,NULL,0,0),(31,'2017-06-15 04:43:58','2017-06-15 04:43:58','moolah','AuthorizeNet_AIM',1,1,10000,0,NULL,0,0),(32,'2017-06-15 04:43:58','2017-06-15 04:43:58','Alipay','Alipay_Express',1,1,10000,0,NULL,0,0),(33,'2017-06-15 04:43:58','2017-06-15 04:43:58','Buckaroo','Buckaroo_CreditCard',1,1,10000,0,NULL,0,0),(34,'2017-06-15 04:43:58','2017-06-15 04:43:58','Coinbase','Coinbase',1,1,10000,0,NULL,0,0),(35,'2017-06-15 04:43:58','2017-06-15 04:43:58','DataCash','DataCash',1,1,10000,0,NULL,0,0),(36,'2017-06-15 04:43:58','2017-06-15 04:43:58','Neteller','Neteller',1,2,10000,0,NULL,0,0),(37,'2017-06-15 04:43:58','2017-06-15 04:43:58','Pacnet','Pacnet',1,1,10000,0,NULL,0,0),(38,'2017-06-15 04:43:58','2017-06-15 04:43:58','PaymentSense','PaymentSense',1,2,10000,0,NULL,0,0),(39,'2017-06-15 04:43:58','2017-06-15 04:43:58','Realex','Realex_Remote',1,1,10000,0,NULL,0,0),(40,'2017-06-15 04:43:58','2017-06-15 04:43:58','Sisow','Sisow',1,1,10000,0,NULL,0,0),(41,'2017-06-15 04:43:58','2017-06-15 04:43:58','Skrill','Skrill',1,1,10000,0,NULL,1,0),(42,'2017-06-15 04:43:58','2017-06-15 04:43:58','BitPay','BitPay',1,1,6,0,NULL,1,0),(43,'2017-06-15 04:43:58','2017-06-15 04:43:58','Dwolla','Dwolla',1,1,5,0,NULL,1,0),(44,'2017-06-15 04:43:58','2017-06-15 04:43:58','AGMS','Agms',1,1,10000,0,NULL,0,0),(45,'2017-06-15 04:43:58','2017-06-15 04:43:58','Barclays','BarclaysEpdq\\Essential',1,1,10000,0,NULL,0,0),(46,'2017-06-15 04:43:58','2017-06-15 04:43:58','Cardgate','Cardgate',1,1,10000,0,NULL,0,0),(47,'2017-06-15 04:43:58','2017-06-15 04:43:58','Checkout.com','CheckoutCom',1,1,10000,0,NULL,0,0),(48,'2017-06-15 04:43:58','2017-06-15 04:43:58','Creditcall','Creditcall',1,1,10000,0,NULL,0,0),(49,'2017-06-15 04:43:58','2017-06-15 04:43:58','Cybersource','Cybersource',1,1,10000,0,NULL,0,0),(50,'2017-06-15 04:43:58','2017-06-15 04:43:58','ecoPayz','Ecopayz',1,1,10000,0,NULL,0,0),(51,'2017-06-15 04:43:58','2017-06-15 04:43:58','Fasapay','Fasapay',1,1,10000,0,NULL,0,0),(52,'2017-06-15 04:43:58','2017-06-15 04:43:58','Komoju','Komoju',1,1,10000,0,NULL,0,0),(53,'2017-06-15 04:43:58','2017-06-15 04:43:58','Multicards','Multicards',1,1,10000,0,NULL,0,0),(54,'2017-06-15 04:43:58','2017-06-15 04:43:58','Pagar.Me','Pagarme',1,2,10000,0,NULL,0,0),(55,'2017-06-15 04:43:58','2017-06-15 04:43:58','Paysafecard','Paysafecard',1,1,10000,0,NULL,0,0),(56,'2017-06-15 04:43:58','2017-06-15 04:43:58','Paytrace','Paytrace_CreditCard',1,1,10000,0,NULL,0,0),(57,'2017-06-15 04:43:58','2017-06-15 04:43:58','Secure Trading','SecureTrading',1,1,10000,0,NULL,0,0),(58,'2017-06-15 04:43:58','2017-06-15 04:43:58','SecPay','SecPay',1,1,10000,0,NULL,0,0),(59,'2017-06-15 04:43:58','2017-06-15 04:43:58','WeChat Express','WeChat_Express',1,2,10000,0,NULL,0,0),(60,'2017-06-15 04:43:58','2017-06-15 04:43:58','WePay','WePay',1,1,10000,0,NULL,0,0),(61,'2017-06-15 04:43:58','2017-06-15 04:43:58','Braintree','Braintree',1,1,2,0,NULL,0,0),(62,'2017-06-15 04:43:58','2017-06-15 04:43:58','Custom','Custom',1,1,8,0,NULL,1,0);
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
) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `industries`
--

LOCK TABLES `industries` WRITE;
/*!40000 ALTER TABLE `industries` DISABLE KEYS */;
INSERT INTO `industries` VALUES (1,'Accounting & Legal'),(2,'Advertising'),(3,'Aerospace'),(4,'Agriculture'),(5,'Automotive'),(6,'Banking & Finance'),(7,'Biotechnology'),(8,'Broadcasting'),(9,'Business Services'),(10,'Commodities & Chemicals'),(11,'Communications'),(12,'Computers & Hightech'),(13,'Defense'),(14,'Energy'),(15,'Entertainment'),(16,'Government'),(17,'Healthcare & Life Sciences'),(18,'Insurance'),(19,'Manufacturing'),(20,'Marketing'),(21,'Media'),(22,'Nonprofit & Higher Ed'),(23,'Pharmaceuticals'),(24,'Professional Services & Consulting'),(25,'Real Estate'),(26,'Retail & Wholesale'),(27,'Sports'),(28,'Transportation'),(29,'Travel & Luxury'),(30,'Other'),(31,'Photography'),(32,'Construction');
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `transaction_reference` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `sent_date` timestamp NULL DEFAULT NULL,
  `viewed_date` timestamp NULL DEFAULT NULL,
  `public_id` int(10) unsigned NOT NULL,
  `opened_date` timestamp NULL DEFAULT NULL,
  `message_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `email_error` text COLLATE utf8_unicode_ci,
  `signature_base64` text COLLATE utf8_unicode_ci,
  `signature_date` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invitations_account_id_public_id_unique` (`account_id`,`public_id`),
  UNIQUE KEY `invitations_invitation_key_unique` (`invitation_key`),
  KEY `invitations_user_id_foreign` (`user_id`),
  KEY `invitations_contact_id_foreign` (`contact_id`),
  KEY `invitations_invoice_id_index` (`invoice_id`),
  KEY `invitations_public_id_index` (`public_id`),
  CONSTRAINT `invitations_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invitations_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
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
-- Table structure for table `invoice_designs`
--

DROP TABLE IF EXISTS `invoice_designs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `invoice_designs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `javascript` mediumtext COLLATE utf8_unicode_ci,
  `pdfmake` mediumtext COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_designs`
--

LOCK TABLES `invoice_designs` WRITE;
/*!40000 ALTER TABLE `invoice_designs` DISABLE KEYS */;
INSERT INTO `invoice_designs` VALUES (1,'Clean','var GlobalY=0;//Y position of line at current page\n\n	    var client = invoice.client;\n	    var account = invoice.account;\n	    var currencyId = client.currency_id;\n\n	    layout.headerRight = 550;\n	    layout.rowHeight = 15;\n\n	    doc.setFontSize(9);\n\n	    if (invoice.image)\n	    {\n	      var left = layout.headerRight - invoice.imageWidth;\n	      doc.addImage(invoice.image, \'JPEG\', layout.marginLeft, 30);\n	    }\n	  \n	    if (!invoice.is_pro && logoImages.imageLogo1)\n	    {\n	      pageHeight=820;\n	      y=pageHeight-logoImages.imageLogoHeight1;\n	      doc.addImage(logoImages.imageLogo1, \'JPEG\', layout.marginLeft, y, logoImages.imageLogoWidth1, logoImages.imageLogoHeight1);\n	    }\n\n	    doc.setFontSize(9);\n	    SetPdfColor(\'LightBlue\', doc, \'primary\');\n	    displayAccount(doc, invoice, 220, layout.accountTop, layout);\n\n	    SetPdfColor(\'LightBlue\', doc, \'primary\');\n	    doc.setFontSize(\'11\');\n	    doc.text(50, layout.headerTop, (invoice.is_quote ? invoiceLabels.quote : invoiceLabels.invoice).toUpperCase());\n\n\n	    SetPdfColor(\'Black\',doc); //set black color\n	    doc.setFontSize(9);\n\n	    var invoiceHeight = displayInvoice(doc, invoice, 50, 170, layout);\n	    var clientHeight = displayClient(doc, invoice, 220, 170, layout);\n	    var detailsHeight = Math.max(invoiceHeight, clientHeight);\n	    layout.tableTop = Math.max(layout.tableTop, layout.headerTop + detailsHeight + (3 * layout.rowHeight));\n	   \n	    doc.setLineWidth(0.3);        \n	    doc.setDrawColor(200,200,200);\n	    doc.line(layout.marginLeft - layout.tablePadding, layout.headerTop + 6, layout.marginRight + layout.tablePadding, layout.headerTop + 6);\n	    doc.line(layout.marginLeft - layout.tablePadding, layout.headerTop + detailsHeight + 14, layout.marginRight + layout.tablePadding, layout.headerTop + detailsHeight + 14);\n\n	    doc.setFontSize(10);\n	    doc.setFontType(\'bold\');\n	    displayInvoiceHeader(doc, invoice, layout);\n	    var y = displayInvoiceItems(doc, invoice, layout);\n\n	    doc.setFontSize(9);\n	    doc.setFontType(\'bold\');\n\n	    GlobalY=GlobalY+25;\n\n\n	    doc.setLineWidth(0.3);\n	    doc.setDrawColor(241,241,241);\n	    doc.setFillColor(241,241,241);\n	    var x1 = layout.marginLeft - 12;\n	    var y1 = GlobalY-layout.tablePadding;\n\n	    var w2 = 510 + 24;\n	    var h2 = doc.internal.getFontSize()*3+layout.tablePadding*2;\n\n	    if (invoice.discount) {\n	        h2 += doc.internal.getFontSize()*2;\n	    }\n	    if (invoice.tax_amount) {\n	        h2 += doc.internal.getFontSize()*2;\n	    }\n\n	    //doc.rect(x1, y1, w2, h2, \'FD\');\n\n	    doc.setFontSize(9);\n	    displayNotesAndTerms(doc, layout, invoice, y);\n	    y += displaySubtotals(doc, layout, invoice, y, layout.unitCostRight);\n\n\n	    doc.setFontSize(10);\n	    Msg = invoice.is_quote ? invoiceLabels.total : invoiceLabels.balance_due;\n	    var TmpMsgX = layout.unitCostRight-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());\n	    \n	    doc.text(TmpMsgX, y, Msg);\n\n	    SetPdfColor(\'LightBlue\', doc, \'primary\');\n	    AmountText = formatMoney(invoice.balance_amount, currencyId);\n	    headerLeft=layout.headerRight+400;\n	    var AmountX = layout.lineTotalRight - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());\n	    doc.text(AmountX, y, AmountText);','{\n    \"content\": [{\n        \"columns\": [\n            {\n                \"image\": \"$accountLogo\",\n                \"fit\": [120, 80]\n            },\n            {\n                \"stack\": \"$accountDetails\",\n                \"margin\": [7, 0, 0, 0]\n            },\n            {\n                \"stack\": \"$accountAddress\"\n            }\n        ]\n    },\n    {\n        \"text\": \"$entityTypeUC\",\n        \"margin\": [8, 30, 8, 5],\n        \"style\": \"entityTypeLabel\"\n        \n    },\n    {\n        \"table\": {\n            \"headerRows\": 1,\n            \"widths\": [\"auto\", \"auto\", \"*\"],\n            \"body\": [\n                [\n                {\n                    \"table\": { \n                        \"body\": \"$invoiceDetails\"\n                    },\n                    \"margin\": [0, 0, 12, 0],\n                    \"layout\": \"noBorders\"\n                }, \n                {\n                    \"stack\": \"$clientDetails\"\n                },\n                {\n                    \"text\": \"\"\n                }\n                ]\n            ]\n        },\n        \"layout\": {\n            \"hLineWidth\": \"$firstAndLast:.5\",\n            \"vLineWidth\": \"$none\",\n            \"hLineColor\": \"#D8D8D8\",\n            \"paddingLeft\": \"$amount:8\", \n            \"paddingRight\": \"$amount:8\", \n            \"paddingTop\": \"$amount:6\", \n            \"paddingBottom\": \"$amount:6\"\n        }\n    },\n    {\n        \"style\": \"invoiceLineItemsTable\",\n        \"table\": {\n            \"headerRows\": 1,\n            \"widths\": \"$invoiceLineItemColumns\",\n            \"body\": \"$invoiceLineItems\"\n        },\n        \"layout\": {\n            \"hLineWidth\": \"$notFirst:.5\",\n            \"vLineWidth\": \"$none\",\n            \"hLineColor\": \"#D8D8D8\",\n            \"paddingLeft\": \"$amount:8\", \n            \"paddingRight\": \"$amount:8\", \n            \"paddingTop\": \"$amount:14\", \n            \"paddingBottom\": \"$amount:14\"            \n        }\n    },\n    {\n        \"columns\": [        \n            \"$notesAndTerms\",\n            {\n                \"table\": {\n                    \"widths\": [\"*\", \"40%\"],\n                    \"body\": \"$subtotals\"\n                },\n                \"layout\": {\n                    \"hLineWidth\": \"$none\",\n                    \"vLineWidth\": \"$none\",\n                    \"paddingLeft\": \"$amount:34\", \n                    \"paddingRight\": \"$amount:8\", \n                    \"paddingTop\": \"$amount:4\", \n                    \"paddingBottom\": \"$amount:4\" \n                }\n            }\n        ]\n    },\n    {\n        \"stack\": [\n            \"$invoiceDocuments\"\n        ],\n        \"style\": \"invoiceDocuments\"\n    }\n    ],\n    \"defaultStyle\": {\n        \"font\": \"$bodyFont\",\n        \"fontSize\": \"$fontSize\",\n        \"margin\": [8, 4, 8, 4]\n    },\n    \"footer\": {\n        \"columns\": [\n            {\n                \"text\": \"$invoiceFooter\",\n                \"alignment\": \"left\"\n            }\n        ],\n        \"margin\": [40, -20, 40, 0]\n    },\n    \"styles\": {\n        \"entityTypeLabel\": {\n            \"font\": \"$headerFont\",\n            \"fontSize\": \"$fontSizeLargest\",\n            \"color\": \"$primaryColor:#37a3c6\"\n        },\n        \"primaryColor\":{\n            \"color\": \"$primaryColor:#37a3c6\"\n        },\n        \"accountName\": {\n            \"color\": \"$primaryColor:#37a3c6\",\n            \"bold\": true\n        },\n        \"invoiceDetails\": {\n            \"margin\": [0, 0, 8, 0]\n        }, \n        \"accountDetails\": {\n            \"margin\": [0, 2, 0, 2]\n        },\n        \"clientDetails\": {\n            \"margin\": [0, 2, 0, 2]\n        },\n        \"notesAndTerms\": {\n            \"margin\": [0, 2, 0, 2]\n        },\n        \"accountAddress\": {\n            \"margin\": [0, 2, 0, 2]\n        },\n        \"odd\": {\n            \"fillColor\": \"#fbfbfb\"\n        },\n        \"productKey\": {\n            \"color\": \"$primaryColor:#37a3c6\",\n            \"bold\": true\n        },\n        \"subtotalsBalanceDueLabel\": {\n            \"fontSize\": \"$fontSizeLarger\"\n        },\n        \"subtotalsBalanceDue\": {\n            \"fontSize\": \"$fontSizeLarger\",\n            \"color\": \"$primaryColor:#37a3c6\"\n        },  \n        \"invoiceNumber\": {\n            \"bold\": true\n        },\n        \"tableHeader\": {\n            \"bold\": true,\n            \"fontSize\": \"$fontSizeLarger\"\n        },\n        \"costTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"qtyTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"taxTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotalTableHeader\": {\n            \"alignment\": \"right\"\n        },        \n        \"invoiceLineItemsTable\": {\n            \"margin\": [0, 16, 0, 16]\n        },\n        \"clientName\": {\n            \"bold\": true\n        },\n        \"cost\": {\n            \"alignment\": \"right\"\n        },\n        \"quantity\": {\n            \"alignment\": \"right\"\n        },\n        \"tax\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotal\": {\n            \"alignment\": \"right\"\n        },\n        \"subtotals\": {\n            \"alignment\": \"right\"\n        },            \n        \"termsLabel\": {\n            \"bold\": true\n        },\n        \"header\": {\n            \"font\": \"$headerFont\",\n            \"fontSize\": \"$fontSizeLargest\",\n            \"bold\": true\n        },\n        \"subheader\": {\n            \"font\": \"$headerFont\",\n            \"fontSize\": \"$fontSizeLarger\"\n        },\n        \"help\": {\n            \"fontSize\": \"$fontSizeSmaller\",\n            \"color\": \"#737373\"\n        },\n        \"invoiceDocuments\": {\n            \"margin\": [7, 0, 7, 0]\n        },\n        \"invoiceDocument\": {\n            \"margin\": [0, 10, 0, 10]\n        }\n    },\n    \"pageMargins\": [40, 40, 40, 60]\n}\n'),(2,'Bold','  var GlobalY=0;//Y position of line at current page\n\n			  var client = invoice.client;\n			  var account = invoice.account;\n			  var currencyId = client.currency_id;\n\n			  layout.headerRight = 150;\n			  layout.rowHeight = 15;\n			  layout.headerTop = 125;\n			  layout.tableTop = 300;\n\n			  doc.setLineWidth(0.5);\n\n			  if (NINJA.primaryColor) {\n			    setDocHexFill(doc, NINJA.primaryColor);\n			    setDocHexDraw(doc, NINJA.primaryColor);\n			  } else {\n			    doc.setFillColor(46,43,43);\n			  }  \n\n			  var x1 =0;\n			  var y1 = 0;\n			  var w2 = 595;\n			  var h2 = 100;\n			  doc.rect(x1, y1, w2, h2, \'FD\');\n\n			  if (invoice.image)\n			  {\n			    var left = layout.headerRight - invoice.imageWidth;\n			    doc.addImage(invoice.image, \'JPEG\', layout.marginLeft, 30);\n			  }\n\n			  doc.setLineWidth(0.5);\n			  if (NINJA.primaryColor) {\n			    setDocHexFill(doc, NINJA.primaryColor);\n			    setDocHexDraw(doc, NINJA.primaryColor);\n			  } else {\n			    doc.setFillColor(46,43,43);\n			    doc.setDrawColor(46,43,43);\n			  }  \n\n			  // return doc.setTextColor(240,240,240);//select color Custom Report GRAY Colour\n			  var x1 = 0;//tableLeft-tablePadding ;\n			  var y1 = 750;\n			  var w2 = 596;\n			  var h2 = 94;//doc.internal.getFontSize()*length+length*1.1;//+h;//+tablePadding;\n\n			  doc.rect(x1, y1, w2, h2, \'FD\');\n			  if (!invoice.is_pro && logoImages.imageLogo2)\n			  {\n			      pageHeight=820;\n			      var left = 250;//headerRight ;\n			      y=pageHeight-logoImages.imageLogoHeight2;\n			      var headerRight=370;\n\n			      var left = headerRight - logoImages.imageLogoWidth2;\n			      doc.addImage(logoImages.imageLogo2, \'JPEG\', left, y, logoImages.imageLogoWidth2, logoImages.imageLogoHeight2);\n			  }\n\n			  doc.setFontSize(7);\n			  doc.setFontType(\'bold\');\n			  SetPdfColor(\'White\',doc);\n\n			  displayAccount(doc, invoice, 300, layout.accountTop, layout);\n\n\n			  var y = layout.accountTop;\n			  var left = layout.marginLeft;\n			  var headerY = layout.headerTop;\n\n			  SetPdfColor(\'GrayLogo\',doc); //set black color\n			  doc.setFontSize(7);\n\n			  //show left column\n			  SetPdfColor(\'Black\',doc); //set black color\n			  doc.setFontType(\'normal\');\n\n			  //publish filled box\n			  doc.setDrawColor(200,200,200);\n\n			  if (NINJA.secondaryColor) {\n			    setDocHexFill(doc, NINJA.secondaryColor);\n			  } else {\n			    doc.setFillColor(54,164,152);  \n			  }  \n\n			  GlobalY=190;\n			  doc.setLineWidth(0.5);\n\n			  var BlockLenght=220;\n			  var x1 =595-BlockLenght;\n			  var y1 = GlobalY-12;\n			  var w2 = BlockLenght;\n			  var h2 = getInvoiceDetailsHeight(invoice, layout) + layout.tablePadding + 2;\n\n			  doc.rect(x1, y1, w2, h2, \'FD\');\n\n			  SetPdfColor(\'SomeGreen\', doc, \'secondary\');\n			  doc.setFontSize(\'14\');\n			  doc.setFontType(\'bold\');\n			  doc.text(50, GlobalY, (invoice.is_quote ? invoiceLabels.your_quote : invoiceLabels.your_invoice).toUpperCase());\n\n\n			  var z=GlobalY;\n			  z=z+30;\n\n			  doc.setFontSize(\'8\');        \n			  SetPdfColor(\'Black\',doc);			  \n        var clientHeight = displayClient(doc, invoice, layout.marginLeft, z, layout);\n        layout.tableTop += Math.max(0, clientHeight - 75);\n			  marginLeft2=395;\n\n			  //publish left side information\n			  SetPdfColor(\'White\',doc);\n			  doc.setFontSize(\'8\');\n			  var detailsHeight = displayInvoice(doc, invoice, marginLeft2, z-25, layout) + 75;\n			  layout.tableTop = Math.max(layout.tableTop, layout.headerTop + detailsHeight + (2 * layout.tablePadding));\n\n			  y=z+60;\n			  x = GlobalY + 100;\n			  doc.setFontType(\'bold\');\n\n			  doc.setFontSize(12);\n			  doc.setFontType(\'bold\');\n			  SetPdfColor(\'Black\',doc);\n			  displayInvoiceHeader(doc, invoice, layout);\n\n			  var y = displayInvoiceItems(doc, invoice, layout);\n			  doc.setLineWidth(0.3);\n			  displayNotesAndTerms(doc, layout, invoice, y);\n			  y += displaySubtotals(doc, layout, invoice, y, layout.unitCostRight);\n\n			  doc.setFontType(\'bold\');\n\n			  doc.setFontSize(12);\n			  x += doc.internal.getFontSize()*4;\n			  Msg = invoice.is_quote ? invoiceLabels.total : invoiceLabels.balance_due;\n			  var TmpMsgX = layout.unitCostRight-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());\n\n			  doc.text(TmpMsgX, y, Msg);\n\n			  //SetPdfColor(\'LightBlue\',doc);\n			  AmountText = formatMoney(invoice.balance_amount , currencyId);\n			  headerLeft=layout.headerRight+400;\n			  var AmountX = headerLeft - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());\n			  SetPdfColor(\'SomeGreen\', doc, \'secondary\');\n			  doc.text(AmountX, y, AmountText);','{\n    \"content\": [\n    {\n        \"columns\": [\n        {\n            \"width\": 380,\n            \"stack\": [\n            {\"text\":\"$yourInvoiceLabelUC\", \"style\": \"yourInvoice\"},\n            \"$clientDetails\"\n            ],\n            \"margin\": [60, 100, 0, 10]\n        },\n        {\n            \"canvas\": [\n            { \n                \"type\": \"rect\", \n                \"x\": 0, \n                \"y\": 0, \n                \"w\": 225, \n                \"h\": \"$invoiceDetailsHeight\",\n                \"r\":0, \n                \"lineWidth\": 1,\n                \"color\": \"$primaryColor:#36a498\"\n            }\n            ],\n            \"width\":10,\n            \"margin\":[-10,100,0,10]\n        },\n        {	\n            \"table\": { \n                \"body\": \"$invoiceDetails\"\n            },\n            \"layout\": \"noBorders\",\n            \"margin\": [0, 110, 0, 0]\n        }\n        ]\n    },\n    {\n        \"style\": \"invoiceLineItemsTable\",\n        \"table\": {\n            \"headerRows\": 1,\n            \"widths\": [\"22%\", \"*\", \"14%\", \"$quantityWidth\", \"$taxWidth\", \"22%\"],\n            \"body\": \"$invoiceLineItems\"\n        },\n        \"layout\": {\n            \"hLineWidth\": \"$none\",\n            \"vLineWidth\": \"$none\",\n            \"paddingLeft\": \"$amount:8\", \n            \"paddingRight\": \"$amount:8\", \n            \"paddingTop\": \"$amount:14\", \n            \"paddingBottom\": \"$amount:14\"\n        }\n    },\n    {\n        \"columns\": [\n        {\n            \"width\": 46,\n            \"text\": \" \"\n        },\n        \"$notesAndTerms\",\n        {\n            \"table\": {\n                \"widths\": [\"*\", \"40%\"],\n                \"body\": \"$subtotals\"\n            },\n            \"layout\": {\n                \"hLineWidth\": \"$none\",\n                \"vLineWidth\": \"$none\",\n                \"paddingLeft\": \"$amount:8\", \n                \"paddingRight\": \"$amount:8\", \n                \"paddingTop\": \"$amount:4\", \n                \"paddingBottom\": \"$amount:4\"  \n            }\n        }]\n    },\n        {\n            \"stack\": [\n                \"$invoiceDocuments\"\n            ],\n            \"style\": \"invoiceDocuments\"\n        }\n    ],\n    \"footer\":\n    [\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 0, \"y1\": 0, \"x2\": 600, \"y2\": 0,\"lineWidth\": 100,\"lineColor\":\"$secondaryColor:#292526\"}]},\n        {\n            \"columns\":\n                [\n                    {\n                        \"text\": \"$invoiceFooter\",\n                        \"margin\": [40, -40, 40, 0],\n                        \"alignment\": \"left\",\n                        \"color\": \"#FFFFFF\"\n                    }\n                ]\n        }\n    ],\n    \"header\": [\n          {\n            \"canvas\": [\n              {\n                \"type\": \"line\",\n                \"x1\": 0,\n                \"y1\": 0,\n                \"x2\": 600,\n                \"y2\": 0,\n                \"lineWidth\": 200,\n                \"lineColor\": \"$secondaryColor:#292526\"\n              }\n            ],\n            \"width\": 10\n          },\n          {\n            \"columns\": [\n              { \n                \"image\": \"$accountLogo\",\n                \"fit\": [120, 60],\n                \"margin\": [30, 16, 0, 0]\n              },\n              {\n                \"stack\": \"$accountDetails\",\n                \"margin\": [\n                  0,\n                  16,\n                  0,\n                  0\n                ],\n                \"width\": 140\n              },\n              {\n                \"stack\": \"$accountAddress\",\n                \"margin\": [\n                  20,\n                  16,\n                  0,\n                  0\n                ]\n              }\n            ]\n          }\n        ],\n    \"defaultStyle\": {\n            \"font\": \"$bodyFont\",\n            \"fontSize\": \"$fontSize\",\n            \"margin\": [8, 4, 8, 4]\n        },\n        \"styles\": {\n            \"primaryColor\":{\n                \"color\": \"$primaryColor:#36a498\"\n            },\n            \"accountName\": {\n                \"bold\": true,\n                \"margin\": [4, 2, 4, 1],\n                \"color\": \"$primaryColor:#36a498\"\n            },\n            \"accountDetails\": {\n                \"margin\": [4, 2, 4, 1],\n                \"color\": \"#FFFFFF\"\n            },\n            \"accountAddress\": {\n                \"margin\": [4, 2, 4, 1],\n                \"color\": \"#FFFFFF\"\n            },\n            \"clientDetails\": {\n                \"margin\": [0, 2, 0, 1]\n            },\n            \"odd\": {\n                \"fillColor\": \"#ebebeb\",\n                \"margin\": [0,0,0,0]\n            },\n            \"productKey\": {\n                \"color\": \"$primaryColor:#36a498\"\n            },\n            \"subtotalsBalanceDueLabel\": {\n                \"fontSize\": \"$fontSizeLargest\",\n                \"bold\": true\n            },\n            \"subtotalsBalanceDue\": {\n                \"fontSize\": \"$fontSizeLargest\",\n                \"color\": \"$primaryColor:#36a498\",\n                \"bold\": true\n            },\n            \"invoiceDetails\": {\n                \"color\": \"#ffffff\"\n            },\n            \"invoiceNumber\": {\n                \"bold\": true\n            },\n            \"itemTableHeader\": {\n                \"margin\": [40,0,0,0]\n            },\n            \"totalTableHeader\": {\n                \"margin\": [0,0,40,0]\n            },\n            \"tableHeader\": {\n                \"fontSize\": 12,\n                \"bold\": true\n            },\n            \"costTableHeader\": {\n                \"alignment\": \"right\"\n            },\n            \"qtyTableHeader\": {\n                \"alignment\": \"right\"\n            },\n            \"taxTableHeader\": {\n                \"alignment\": \"right\"\n            },\n            \"lineTotalTableHeader\": {\n                \"alignment\": \"right\",\n                \"margin\": [0, 0, 40, 0]\n            },\n            \"productKey\": {\n                \"color\": \"$primaryColor:#36a498\",\n                \"margin\": [40,0,0,0],\n                \"bold\": true\n            },\n            \"yourInvoice\": {\n                \"font\": \"$headerFont\",\n                \"bold\": true, \n                \"fontSize\": 14, \n                \"color\": \"$primaryColor:#36a498\",\n                \"margin\": [0,0,0,8]\n            },\n            \"invoiceLineItemsTable\": {\n                \"margin\": [0, 26, 0, 16]\n            },\n            \"clientName\": {\n                \"bold\": true\n            },\n            \"cost\": {\n                \"alignment\": \"right\"\n            },\n            \"quantity\": {\n                \"alignment\": \"right\"\n            },\n            \"tax\": {\n                \"alignment\": \"right\"\n            },\n            \"lineTotal\": {\n                \"alignment\": \"right\",\n                \"margin\": [0, 0, 40, 0]\n            },\n            \"subtotals\": {\n                \"alignment\": \"right\",\n                \"margin\": [0,0,40,0]\n            },\n            \"termsLabel\": {\n                \"bold\": true,\n                \"margin\": [0, 0, 0, 4]\n            },\n            \"header\": {\n                \"font\": \"$headerFont\",\n                \"fontSize\": \"$fontSizeLargest\",\n                \"bold\": true\n            },\n            \"subheader\": {\n                \"font\": \"$headerFont\",\n                \"fontSize\": \"$fontSizeLarger\"\n            },\n            \"help\": {\n                \"fontSize\": \"$fontSizeSmaller\",\n                \"color\": \"#737373\"\n            },\n            \"invoiceDocuments\": {\n                \"margin\": [47, 0, 47, 0]\n            },\n            \"invoiceDocument\": {\n                \"margin\": [0, 10, 0, 10]\n            }\n        },\n        \"pageMargins\": [0, 80, 0, 40]\n    }'),(3,'Modern','    var client = invoice.client;\n	    var account = invoice.account;\n	    var currencyId = client.currency_id;\n\n	    layout.headerRight = 400;\n	    layout.rowHeight = 15;\n\n\n	    doc.setFontSize(7);\n\n	    // add header\n	    doc.setLineWidth(0.5);\n\n	    if (NINJA.primaryColor) {\n	      setDocHexFill(doc, NINJA.primaryColor);\n	      setDocHexDraw(doc, NINJA.primaryColor);\n	    } else {\n	      doc.setDrawColor(242,101,34);\n	      doc.setFillColor(242,101,34);\n	    }  \n\n	    var x1 =0;\n	    var y1 = 0;\n	    var w2 = 595;\n	    var h2 = Math.max(110, getInvoiceDetailsHeight(invoice, layout) + 30);\n	    doc.rect(x1, y1, w2, h2, \'FD\');\n\n	    SetPdfColor(\'White\',doc);\n\n	    //second column\n	    doc.setFontType(\'bold\');\n	    var name = invoice.account.name;    \n	    if (name) {\n	        doc.setFontSize(\'30\');\n	        doc.setFontType(\'bold\');\n	        doc.text(40, 50, name);\n	    }\n\n	    if (invoice.image)\n	    {\n	        y=130;\n	        var left = layout.headerRight - invoice.imageWidth;\n	        doc.addImage(invoice.image, \'JPEG\', layout.marginLeft, y);\n	    }\n\n	    // add footer \n	    doc.setLineWidth(0.5);\n\n	    if (NINJA.primaryColor) {\n	      setDocHexFill(doc, NINJA.primaryColor);\n	      setDocHexDraw(doc, NINJA.primaryColor);\n	    } else {\n	      doc.setDrawColor(242,101,34);\n	      doc.setFillColor(242,101,34);\n	    }  \n\n	    var x1 = 0;//tableLeft-tablePadding ;\n	    var y1 = 750;\n	    var w2 = 596;\n	    var h2 = 94;//doc.internal.getFontSize()*length+length*1.1;//+h;//+tablePadding;\n\n	    doc.rect(x1, y1, w2, h2, \'FD\');\n\n	    if (!invoice.is_pro && logoImages.imageLogo3)\n	    {\n	        pageHeight=820;\n	      // var left = 25;//250;//headerRight ;\n	        y=pageHeight-logoImages.imageLogoHeight3;\n	        //var headerRight=370;\n\n	        //var left = headerRight - invoice.imageLogoWidth3;\n	        doc.addImage(logoImages.imageLogo3, \'JPEG\', 40, y, logoImages.imageLogoWidth3, logoImages.imageLogoHeight3);\n	    }\n\n	    doc.setFontSize(10);  \n	    var marginLeft = 340;\n	    displayAccount(doc, invoice, marginLeft, 780, layout);\n\n\n	    SetPdfColor(\'White\',doc);    \n	    doc.setFontSize(\'8\');\n	    var detailsHeight = displayInvoice(doc, invoice, layout.headerRight, layout.accountTop-10, layout);\n	    layout.headerTop = Math.max(layout.headerTop, detailsHeight + 50);\n	    layout.tableTop = Math.max(layout.tableTop, detailsHeight + 150);\n\n	    SetPdfColor(\'Black\',doc); //set black color\n	    doc.setFontSize(7);\n	    doc.setFontType(\'normal\');\n	    displayClient(doc, invoice, layout.headerRight, layout.headerTop, layout);\n\n\n	      \n	    SetPdfColor(\'White\',doc);    \n	    doc.setFontType(\'bold\');\n\n	    doc.setLineWidth(0.3);\n	    if (NINJA.secondaryColor) {\n	      setDocHexFill(doc, NINJA.secondaryColor);\n	      setDocHexDraw(doc, NINJA.secondaryColor);\n	    } else {\n	      doc.setDrawColor(63,60,60);\n	      doc.setFillColor(63,60,60);\n	    }  \n\n	    var left = layout.marginLeft - layout.tablePadding;\n	    var top = layout.tableTop - layout.tablePadding;\n	    var width = layout.marginRight - (2 * layout.tablePadding);\n	    var height = 20;\n	    doc.rect(left, top, width, height, \'FD\');\n	    \n\n	    displayInvoiceHeader(doc, invoice, layout);\n	    SetPdfColor(\'Black\',doc);\n	    var y = displayInvoiceItems(doc, invoice, layout);\n\n\n	    var height1 = displayNotesAndTerms(doc, layout, invoice, y);\n	    var height2 = displaySubtotals(doc, layout, invoice, y, layout.unitCostRight);\n	    y += Math.max(height1, height2);\n\n\n	    var left = layout.marginLeft - layout.tablePadding;\n	    var top = y - layout.tablePadding;\n	    var width = layout.marginRight - (2 * layout.tablePadding);\n	    var height = 20;\n	    if (NINJA.secondaryColor) {\n	      setDocHexFill(doc, NINJA.secondaryColor);\n	      setDocHexDraw(doc, NINJA.secondaryColor);\n	    } else {\n	      doc.setDrawColor(63,60,60);\n	      doc.setFillColor(63,60,60);\n	    }  \n	    doc.rect(left, top, width, height, \'FD\');\n	    \n	    doc.setFontType(\'bold\');\n	    SetPdfColor(\'White\', doc);\n	    doc.setFontSize(12);\n	    \n	    var label = invoice.is_quote ? invoiceLabels.total : invoiceLabels.balance_due;\n	    var labelX = layout.unitCostRight-(doc.getStringUnitWidth(label) * doc.internal.getFontSize());\n	    doc.text(labelX, y+2, label);\n\n\n	    doc.setFontType(\'normal\');\n	    var amount = formatMoney(invoice.balance_amount , currencyId);\n	    headerLeft=layout.headerRight+400;\n	    var amountX = layout.lineTotalRight - (doc.getStringUnitWidth(amount) * doc.internal.getFontSize());\n	    doc.text(amountX, y+2, amount);','{\n    \"content\": [\n        {\n            \"columns\": [\n            {\n                \"image\": \"$accountLogo\",\n                \"fit\": [120, 80],\n                \"margin\": [0, 60, 0, 30]\n            },\n            {\n                \"stack\": \"$clientDetails\",\n                \"margin\": [0, 60, 0, 0]\n            }\n            ]\n        },\n        {\n            \"style\": \"invoiceLineItemsTable\",\n            \"table\": {\n                \"headerRows\": 1,\n                \"widths\": \"$invoiceLineItemColumns\",\n                \"body\": \"$invoiceLineItems\"\n            },\n            \"layout\": {\n                \"hLineWidth\": \"$notFirst:.5\",\n                \"vLineWidth\": \"$notFirstAndLastColumn:.5\",\n                \"hLineColor\": \"#888888\",\n                \"vLineColor\": \"#FFFFFF\",\n                \"paddingLeft\": \"$amount:8\",\n                \"paddingRight\": \"$amount:8\",\n                \"paddingTop\": \"$amount:8\",\n                \"paddingBottom\": \"$amount:8\"\n            }\n        },\n        {\n            \"columns\": [\n            \"$notesAndTerms\",\n            {\n                \"table\": {\n                    \"widths\": [\"*\", \"40%\"],\n                    \"body\": \"$subtotalsWithoutBalance\"\n                },\n                \"layout\": {\n                    \"hLineWidth\": \"$none\",\n                    \"vLineWidth\": \"$none\",\n                    \"paddingLeft\": \"$amount:34\",\n                    \"paddingRight\": \"$amount:8\",\n                    \"paddingTop\": \"$amount:4\",\n                    \"paddingBottom\": \"$amount:4\"\n                }\n            }\n            ]\n        },\n        {\n            \"columns\": [\n            {\n                \"canvas\": [\n                {\n                    \"type\": \"rect\",\n                    \"x\": 0,\n                    \"y\": 0,\n                    \"w\": 515,\n                    \"h\": 26,\n                    \"r\": 0,\n                    \"lineWidth\": 1,\n                    \"color\": \"$secondaryColor:#403d3d\"\n                }\n                ],\n                \"width\": 10,\n                \"margin\": [\n                0,\n                10,\n                0,\n                0\n                ]\n            },\n            {\n                \"text\": \"$balanceDueLabel\",\n                \"style\": \"subtotalsBalanceDueLabel\",\n                \"margin\": [0, 16, 0, 0],\n                \"width\": 370\n            },\n            {\n                \"text\": \"$balanceDue\",\n                \"style\": \"subtotalsBalanceDue\",\n                \"margin\": [0, 16, 8, 0]\n            }\n            ]\n        },\n        {\n            \"stack\": [\n                \"$invoiceDocuments\"\n            ],\n            \"style\": \"invoiceDocuments\"\n        }\n    ],\n    \"footer\": [\n    {\n        \"canvas\": [\n        {\n            \"type\": \"line\", \"x1\": 0, \"y1\": 0, \"x2\": 600, \"y2\": 0,\"lineWidth\": 100,\"lineColor\":\"$primaryColor:#f26621\"\n            }]\n            ,\"width\":10\n        },\n        {\n        \"columns\": [\n        {\n            \"width\": 350,\n            \"stack\": [\n            {\n                \"text\": \"$invoiceFooter\",\n                \"margin\": [40, -40, 40, 0],\n                \"alignment\": \"left\",\n                \"color\": \"#FFFFFF\"\n\n            }\n            ]\n        },\n        {\n            \"stack\": \"$accountDetails\",\n            \"margin\": [0, -40, 0, 0],\n            \"width\": \"*\"\n        },\n        {\n            \"stack\": \"$accountAddress\",\n            \"margin\": [0, -40, 0, 0],\n            \"width\": \"*\"\n        }\n        ]\n    }\n    ],\n    \"header\": [\n    {\n        \"canvas\": [{ \"type\": \"line\", \"x1\": 0, \"y1\": 0, \"x2\": 600, \"y2\": 0,\"lineWidth\": 200,\"lineColor\":\"$primaryColor:#f26621\"}],\"width\":10\n    },\n    {\n        \"columns\": [\n        {\n            \"text\": \"$accountName\", \"bold\": true,\"font\":\"$headerFont\",\"fontSize\":30,\"color\":\"#ffffff\",\"margin\":[40,20,0,0],\"width\":350\n        }\n        ]\n    },\n    {\n        \"width\": 300,\n        \"table\": {\n            \"body\": \"$invoiceDetails\"\n        },\n        \"layout\": \"noBorders\",\n        \"margin\": [400, -40, 0, 0]\n    }\n    ],\n    \"defaultStyle\": {\n        \"font\": \"$bodyFont\",\n        \"fontSize\": \"$fontSize\",\n        \"margin\": [8, 4, 8, 4]\n    },\n    \"styles\": {\n        \"primaryColor\":{\n            \"color\": \"$primaryColor:#299CC2\"\n        },\n        \"accountName\": {\n            \"margin\": [4, 2, 4, 2],\n            \"color\": \"$primaryColor:#299CC2\"\n        },\n        \"accountDetails\": {\n            \"margin\": [4, 2, 4, 2],\n            \"color\": \"#FFFFFF\"\n        },\n        \"accountAddress\": {\n            \"margin\": [4, 2, 4, 2],\n            \"color\": \"#FFFFFF\"\n        },\n        \"clientDetails\": {\n            \"margin\": [0, 2, 4, 2]\n        },\n        \"invoiceDetails\": {\n            \"color\": \"#FFFFFF\"\n        },\n        \"invoiceLineItemsTable\": {\n            \"margin\": [0, 0, 0, 16]\n        },\n        \"productKey\": {\n            \"bold\": true\n        },\n        \"clientName\": {\n            \"bold\": true\n        },\n        \"tableHeader\": {\n            \"bold\": true,\n            \"color\": \"#FFFFFF\",\n            \"fontSize\": \"$fontSizeLargest\",\n            \"fillColor\": \"$secondaryColor:#403d3d\"\n        },\n        \"costTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"qtyTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"taxTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotalTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"subtotalsBalanceDueLabel\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"color\":\"#FFFFFF\",\n            \"alignment\":\"right\",\n            \"bold\": true\n        },\n        \"subtotalsBalanceDue\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"color\":\"#FFFFFF\",\n            \"bold\": true,\n            \"alignment\":\"right\"\n        },\n        \"cost\": {\n            \"alignment\": \"right\"\n        },\n        \"quantity\": {\n            \"alignment\": \"right\"\n        },\n        \"tax\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotal\": {\n            \"alignment\": \"right\"\n        },\n        \"subtotals\": {\n            \"alignment\": \"right\"\n        },\n        \"termsLabel\": {\n            \"bold\": true,\n            \"margin\": [0, 0, 0, 4]\n        },\n        \"invoiceNumberLabel\": {\n            \"bold\": true\n        },\n        \"invoiceNumber\": {\n            \"bold\": true\n        },\n        \"header\": {\n            \"font\": \"$headerFont\",\n            \"fontSize\": \"$fontSizeLargest\",\n            \"bold\": true\n        },\n        \"subheader\": {\n            \"font\": \"$headerFont\",\n            \"fontSize\": \"$fontSizeLarger\"\n        },\n        \"help\": {\n            \"fontSize\": \"$fontSizeSmaller\",\n            \"color\": \"#737373\"\n        },\n        \"invoiceDocuments\": {\n            \"margin\": [7, 0, 7, 0]\n        },\n        \"invoiceDocument\": {\n            \"margin\": [0, 10, 0, 10]\n        }\n    },\n    \"pageMargins\": [40, 120, 40, 50]\n}\n'),(4,'Plain','  var client = invoice.client;\n		  var account = invoice.account;\n		  var currencyId = client.currency_id;  \n		  \n      layout.accountTop += 25;\n      layout.headerTop += 25;\n      layout.tableTop += 25;\n\n		  if (invoice.image)\n		  {\n		    var left = layout.headerRight - invoice.imageWidth;\n		    doc.addImage(invoice.image, \'JPEG\', left, 50);\n		  } \n		  \n		  /* table header */\n		  doc.setDrawColor(200,200,200);\n		  doc.setFillColor(230,230,230);\n		  \n		  var detailsHeight = getInvoiceDetailsHeight(invoice, layout);\n		  var left = layout.headerLeft - layout.tablePadding;\n		  var top = layout.headerTop + detailsHeight - layout.rowHeight - layout.tablePadding;\n		  var width = layout.headerRight - layout.headerLeft + (2 * layout.tablePadding);\n		  var height = layout.rowHeight + 1;\n		  doc.rect(left, top, width, height, \'FD\'); \n\n		  doc.setFontSize(10);\n		  doc.setFontType(\'normal\');\n\n		  displayAccount(doc, invoice, layout.marginLeft, layout.accountTop, layout);\n		  displayClient(doc, invoice, layout.marginLeft, layout.headerTop, layout);\n\n		  displayInvoice(doc, invoice, layout.headerLeft, layout.headerTop, layout, layout.headerRight);\n		  layout.tableTop = Math.max(layout.tableTop, layout.headerTop + detailsHeight + (2 * layout.tablePadding));\n\n		  var headerY = layout.headerTop;\n		  var total = 0;\n\n		  doc.setDrawColor(200,200,200);\n		  doc.setFillColor(230,230,230);\n		  var left = layout.marginLeft - layout.tablePadding;\n		  var top = layout.tableTop - layout.tablePadding;\n		  var width = layout.headerRight - layout.marginLeft + (2 * layout.tablePadding);\n		  var height = layout.rowHeight + 2;\n		  doc.rect(left, top, width, height, \'FD\');   \n\n		  displayInvoiceHeader(doc, invoice, layout);\n		  var y = displayInvoiceItems(doc, invoice, layout);\n\n		  doc.setFontSize(10);\n\n		  displayNotesAndTerms(doc, layout, invoice, y+20);\n\n		  y += displaySubtotals(doc, layout, invoice, y+20, 480) + 20;\n\n		  doc.setDrawColor(200,200,200);\n		  doc.setFillColor(230,230,230);\n		  \n		  var left = layout.footerLeft - layout.tablePadding;\n		  var top = y - layout.tablePadding;\n		  var width = layout.headerRight - layout.footerLeft + (2 * layout.tablePadding);\n		  var height = layout.rowHeight + 2;\n		  doc.rect(left, top, width, height, \'FD\'); \n		  \n		  doc.setFontType(\'bold\');\n		  doc.text(layout.footerLeft, y, invoice.is_quote ? invoiceLabels.total : invoiceLabels.balance_due);\n\n		  total = formatMoney(invoice.balance_amount, currencyId);\n		  var totalX = layout.headerRight - (doc.getStringUnitWidth(total) * doc.internal.getFontSize());\n		  doc.text(totalX, y, total);   \n\n		  if (!invoice.is_pro) {\n		    doc.setFontType(\'normal\');\n		    doc.text(layout.marginLeft, 790, \'Created by InvoiceNinja.com\');\n		  }','{\n    \"content\": [\n    {\n        \"columns\": [\n		{\n            \"stack\": \"$accountDetails\"\n        },\n        {\n            \"stack\": \"$accountAddress\"\n        },\n        [\n            {\n                \"image\": \"$accountLogo\",\n                \"fit\": [120, 80]\n            }\n        ]        \n    ]},\n	{\n	\"columns\": [\n			{\n				\"width\": 340,\n				\"stack\": \"$clientDetails\",\n				\"margin\": [0,40,0,0]\n			},\n			{\n				\"width\":200,\n                \"table\": { \n                    \"body\": \"$invoiceDetails\"\n                },\n                \"layout\": {\n                    \"hLineWidth\": \"$none\",\n                    \"vLineWidth\": \"$none\",\n                    \"hLineColor\": \"#E6E6E6\",\n                    \"paddingLeft\": \"$amount:10\", \n                    \"paddingRight\": \"$amount:10\"\n                }\n			}\n		]\n	},	\n	{\n        \"canvas\": [{ \"type\": \"rect\", \"x\": 0, \"y\": 0, \"w\": 515, \"h\": 25,\"r\":0, \"lineWidth\": 1,\"color\":\"#e6e6e6\"}],\"width\":10,\"margin\":[0,30,0,-43]\n    },\n    {\n        \"style\": \"invoiceLineItemsTable\",\n        \"table\": {\n            \"headerRows\": 1,\n            \"widths\": \"$invoiceLineItemColumns\",\n            \"body\": \"$invoiceLineItems\"\n        },\n        \"layout\": {\n            \"hLineWidth\": \"$notFirst:1\",\n            \"vLineWidth\": \"$none\",\n            \"hLineColor\": \"#e6e6e6\",\n            \"paddingLeft\": \"$amount:8\", \n            \"paddingRight\": \"$amount:8\", \n            \"paddingTop\": \"$amount:8\", \n            \"paddingBottom\": \"$amount:8\"            \n        }\n    },\n    {\n        \"columns\": [\n            \"$notesAndTerms\",\n            {\n                \"width\": 160,\n                \"style\": \"subtotals\",\n                \"table\": {\n                    \"widths\": [60, 60],\n                    \"body\": \"$subtotals\"\n                },\n                \"layout\": {\n                    \"hLineWidth\": \"$none\",\n                    \"vLineWidth\": \"$none\",\n                    \"paddingLeft\": \"$amount:10\", \n                    \"paddingRight\": \"$amount:10\", \n                    \"paddingTop\": \"$amount:4\", \n                    \"paddingBottom\": \"$amount:4\" \n                }\n            }\n        ]\n    },    \n    {\n        \"stack\": [\n            \"$invoiceDocuments\"\n        ],\n        \"style\": \"invoiceDocuments\"\n    }\n    ],\n    \"footer\": {\n        \"columns\": [\n            {\n                \"text\": \"$invoiceFooter\",\n                \"alignment\": \"left\",\n                \"margin\": [0, 0, 0, 12]\n\n            }\n        ],\n        \"margin\": [40, -20, 40, 40]\n    },\n    \"defaultStyle\": {\n        \"font\": \"$bodyFont\",\n        \"fontSize\": \"$fontSize\",\n        \"margin\": [8, 4, 8, 4]\n    },\n    \"styles\": {\n        \"primaryColor\":{\n            \"color\": \"$primaryColor:#299CC2\"\n        },\n        \"accountDetails\": {\n            \"margin\": [0, 2, 0, 1]\n        },\n        \"accountAddress\": {\n            \"margin\": [0, 2, 0, 1]\n        },\n        \"clientDetails\": {\n            \"margin\": [0, 2, 0, 1]\n        },\n        \"tableHeader\": {\n            \"bold\": true\n        },\n        \"costTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"qtyTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotalTableHeader\": {\n            \"alignment\": \"right\"\n        },        \n        \"invoiceLineItemsTable\": {\n            \"margin\": [0, 16, 0, 16]\n        },\n        \"cost\": {\n            \"alignment\": \"right\"\n        },\n        \"quantity\": {\n            \"alignment\": \"right\"\n        },\n        \"tax\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotal\": {\n            \"alignment\": \"right\"\n        },\n        \"subtotals\": {\n            \"alignment\": \"right\"\n        },            \n        \"termsLabel\": {\n            \"bold\": true,\n            \"margin\": [0, 0, 0, 4]\n        },\n        \"terms\": {\n            \"margin\": [0, 0, 20, 0]\n        },\n        \"invoiceDetailBalanceDueLabel\": {\n            \"fillColor\": \"#e6e6e6\"\n        },\n        \"invoiceDetailBalanceDue\": {\n            \"fillColor\": \"#e6e6e6\"\n        },\n        \"subtotalsBalanceDueLabel\": {\n            \"fillColor\": \"#e6e6e6\"\n        },\n        \"subtotalsBalanceDue\": {\n            \"fillColor\": \"#e6e6e6\"\n        },\n        \"header\": {\n            \"font\": \"$headerFont\",\n            \"fontSize\": \"$fontSizeLargest\",\n            \"bold\": true\n        },\n        \"subheader\": {\n            \"font\": \"$headerFont\",\n            \"fontSize\": \"$fontSizeLarger\"\n        },\n        \"help\": {\n            \"fontSize\": \"$fontSizeSmaller\",\n            \"color\": \"#737373\"\n        },\n		\"invoiceDocuments\": {\n			\"margin\": [7, 0, 7, 0]\n		},\n		\"invoiceDocument\": {\n			\"margin\": [0, 10, 0, 10]\n		}\n     },\n    \"pageMargins\": [40, 40, 40, 60]\n}\n'),(5,'Business',NULL,'{\n    \"content\": [\n    {\n        \"columns\":\n        [\n            {\n        		\"image\": \"$accountLogo\",\n        		\"fit\": [120, 80]\n    		},\n            {\n                \"width\": 300,\n                \"stack\": \"$accountDetails\",\n                \"margin\": [140, 0, 0, 0]\n        	},\n        	{\n                \"width\": 150,\n                \"stack\": \"$accountAddress\"\n        	}\n        ]\n    },\n    {\n    	\"columns\": [\n		{\n			\"width\": 120,\n			\"stack\": [\n                {\"text\": \"$invoiceIssuedToLabel\", \"style\":\"issuedTo\"},\n                \"$clientDetails\"\n            ],\n			\"margin\": [0, 20, 0, 0]\n		},\n		{\n            \"canvas\": [{ \"type\": \"rect\", \"x\": 20, \"y\": 0, \"w\": 174, \"h\": \"$invoiceDetailsHeight\",\"r\":10, \"lineWidth\": 1,\"color\":\"$primaryColor:#eb792d\"}],\n            \"width\":36,\n            \"margin\":[200,25,0,0]\n        },\n		{\n            \"table\": {\n                \"widths\": [64, 70],\n                \"body\": \"$invoiceDetails\"\n            },\n            \"layout\": \"noBorders\",\n			\"margin\": [200, 34, 0, 0]\n		}\n	]\n    },\n    {\"canvas\": [{ \"type\": \"rect\", \"x\": 0, \"y\": 0, \"w\": 515, \"h\": 32,\"r\":8, \"lineWidth\": 1,\"color\":\"$secondaryColor:#374e6b\"}],\"width\":10,\"margin\":[0,20,0,-45]},\n    {\n        \"style\": \"invoiceLineItemsTable\",\n        \"table\": {\n            \"headerRows\": 1,\n            \"widths\": \"$invoiceLineItemColumns\",\n            \"body\": \"$invoiceLineItems\"\n        },\n        \"layout\": {\n            \"hLineWidth\": \"$notFirst:1\",\n            \"vLineWidth\": \"$notFirst:.5\",\n            \"hLineColor\": \"#FFFFFF\",\n            \"vLineColor\": \"#FFFFFF\",\n            \"paddingLeft\": \"$amount:8\",\n            \"paddingRight\": \"$amount:8\",\n            \"paddingTop\": \"$amount:12\",\n            \"paddingBottom\": \"$amount:12\"\n        }\n    },\n    {\n    \"columns\": [\n      \"$notesAndTerms\",\n      {\n        \"stack\": [\n          {\n            \"style\": \"subtotals\",\n            \"table\": {\n              \"widths\": [\"*\", \"35%\"],\n              \"body\": \"$subtotalsWithoutBalance\"\n            },\n            \"layout\": {\n              \"hLineWidth\": \"$none\",\n              \"vLineWidth\": \"$none\",\n              \"paddingLeft\": \"$amount:34\",\n              \"paddingRight\": \"$amount:8\",\n              \"paddingTop\": \"$amount:4\",\n              \"paddingBottom\": \"$amount:4\"\n            }\n          },\n        {\n          \"canvas\": [\n          {\n            \"type\": \"rect\",\n            \"x\": 76,\n            \"y\": 20,\n            \"w\": 182,\n            \"h\": 30,\n            \"r\": 7,\n            \"lineWidth\": 1,\n            \"color\": \"$secondaryColor:#374e6b\"\n          }\n        ]\n        },\n          {\n            \"style\": \"subtotalsBalance\",\n            \"table\": {\n                \"widths\": [\"*\", \"35%\"],\n                \"body\": \"$subtotalsBalance\"\n            },\n            \"layout\": {\n              \"hLineWidth\": \"$none\",\n              \"vLineWidth\": \"$none\",\n              \"paddingLeft\": \"$amount:34\",\n              \"paddingRight\": \"$amount:8\",\n              \"paddingTop\": \"$amount:4\",\n              \"paddingBottom\": \"$amount:4\"\n            }\n          }\n        ]\n      }\n    ]\n    },\n    {\n        \"stack\": [\n            \"$invoiceDocuments\"\n        ],\n        \"style\": \"invoiceDocuments\"\n    }\n    ],\n    \"footer\": {\n        \"columns\": [\n            {\n                \"text\": \"$invoiceFooter\",\n                \"alignment\": \"left\"\n            }\n        ],\n        \"margin\": [40, -20, 40, 0]\n    },\n    \"defaultStyle\": {\n        \"fontSize\": \"$fontSize\",\n        \"margin\": [8, 4, 8, 4]\n    },\n    \"styles\": {\n        \"primaryColor\":{\n            \"color\": \"$primaryColor:#299CC2\"\n        },\n        \"accountName\": {\n            \"bold\": true\n        },\n        \"accountDetails\": {\n            \"color\": \"#AAA9A9\",\n            \"margin\": [0,2,0,1]\n        },\n        \"accountAddress\": {\n            \"color\": \"#AAA9A9\",\n            \"margin\": [0,2,0,1]\n        },\n        \"even\": {\n            \"fillColor\":\"#E8E8E8\"\n        },\n        \"odd\": {\n            \"fillColor\":\"#F7F7F7\"\n        },\n        \"productKey\": {\n            \"bold\": true\n        },\n        \"subtotalsBalanceDueLabel\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"color\": \"#ffffff\",\n            \"bold\": true\n        },\n        \"subtotalsBalanceDue\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"bold\": true,\n            \"color\":\"#ffffff\",\n            \"alignment\":\"right\"\n        },\n        \"invoiceDetails\": {\n            \"color\": \"#ffffff\"\n        },\n        \"tableHeader\": {\n            \"color\": \"#ffffff\",\n            \"fontSize\": \"$fontSizeLargest\",\n            \"bold\": true\n        },\n        \"costTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"qtyTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"taxTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotalTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"issuedTo\": {\n            \"margin\": [0,2,0,1],\n            \"bold\": true,\n            \"color\": \"#374e6b\"\n        },\n        \"clientDetails\": {\n            \"margin\": [0,2,0,1]\n        },\n        \"clientName\": {\n            \"color\": \"$primaryColor:#eb792d\"\n        },\n        \"invoiceLineItemsTable\": {\n            \"margin\": [0, 10, 0, 10]\n        },\n        \"invoiceDetailsValue\": {\n            \"alignment\": \"right\"\n        },\n        \"cost\": {\n            \"alignment\": \"right\"\n        },\n        \"quantity\": {\n            \"alignment\": \"right\"\n        },\n        \"tax\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotal\": {\n            \"alignment\": \"right\"\n        },\n        \"subtotals\": {\n            \"alignment\": \"right\"\n        },\n        \"subtotalsBalance\": {\n            \"alignment\": \"right\",\n            \"margin\": [0, -25, 0, 0]\n        },\n         \"termsLabel\": {\n            \"bold\": true,\n            \"margin\": [0, 0, 0, 4]\n        },\n        \"header\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"bold\": true\n        },\n        \"subheader\": {\n            \"fontSize\": \"$fontSizeLarger\"\n        },\n        \"help\": {\n            \"fontSize\": \"$fontSizeSmaller\",\n            \"color\": \"#737373\"\n        }\n    },\n    \"pageMargins\": [40, 40, 40, 40]\n}\n'),(6,'Creative',NULL,'{\n    \"content\": [\n    {\n        \"columns\": [\n    		{\n    			\"stack\": \"$clientDetails\"\n    		},\n            {\n                \"stack\": \"$accountDetails\"\n            },\n            {\n                \"stack\": \"$accountAddress\"\n            },\n            {\n                \"image\": \"$accountLogo\",\n                \"fit\": [120, 80],\n                \"alignment\": \"right\"\n            }\n        ],\n        \"margin\": [0, 0, 0, 20]\n    },\n	{\n		\"columns\": [\n            {\"text\":\n                [\n                    {\"text\": \"$entityTypeUC\", \"style\": \"header1\"},\n                    {\"text\": \"#\", \"style\": \"header2\"},\n                    {\"text\": \"$invoiceNumber\", \"style\":\"header2\"}\n                ],\n                \"width\": \"*\"\n            },\n    		{\n    			\"width\":200,\n                \"table\": {\n                    \"body\": \"$invoiceDetails\"\n                },\n                \"layout\": \"noBorders\",\n    			\"margin\": [16, 4, 0, 0]\n    		}\n		],\n        \"margin\": [0, 0, 0, 20]\n	},\n	{\"canvas\": [{ \"type\": \"line\", \"x1\": 0, \"y1\": 5, \"x2\": 515, \"y2\": 5, \"lineWidth\": 3,\"lineColor\":\"$primaryColor:#AE1E54\"}]},\n    {\n        \"style\": \"invoiceLineItemsTable\",\n        \"table\": {\n            \"headerRows\": 1,\n            \"widths\": \"$invoiceLineItemColumns\",\n            \"body\": \"$invoiceLineItems\"\n        },\n        \"layout\": {\n            \"hLineWidth\": \"$none\",\n            \"vLineWidth\": \"$none\",\n            \"hLineColor\": \"$primaryColor:#E8E8E8\",\n            \"paddingLeft\": \"$amount:8\",\n            \"paddingRight\": \"$amount:8\",\n            \"paddingTop\": \"$amount:8\",\n            \"paddingBottom\": \"$amount:8\"\n        }\n    },\n    {\n        \"columns\": [\n        \"$notesAndTerms\",\n        {\n            \"style\": \"subtotals\",\n            \"table\": {\n                \"widths\": [\"*\", \"40%\"],\n                \"body\": \"$subtotalsWithoutBalance\"\n            },\n            \"layout\": {\n                \"hLineWidth\": \"$none\",\n                \"vLineWidth\": \"$none\",\n                \"paddingLeft\": \"$amount:34\",\n                \"paddingRight\": \"$amount:8\",\n                \"paddingTop\": \"$amount:4\",\n                \"paddingBottom\": \"$amount:4\"\n            }\n        }\n        ]\n    },\n	{\n		\"canvas\": [{ \"type\": \"line\", \"x1\": 0, \"y1\": 20, \"x2\": 515, \"y2\": 20, \"lineWidth\": 3,\"lineColor\":\"$primaryColor:#AE1E54\"}],\n        \"margin\": [0, -8, 0, -8]\n	},\n    {\n        \"text\": \"$balanceDueLabel\",\n        \"style\": \"subtotalsBalanceDueLabel\"\n    },\n    {\n        \"text\": \"$balanceDue\",\n        \"style\": \"subtotalsBalanceDue\"\n    },\n    {\n        \"stack\": [\n            \"$invoiceDocuments\"\n        ],\n        \"style\": \"invoiceDocuments\"\n    }\n    ],\n    \"footer\": {\n        \"columns\": [\n            {\n                \"text\": \"$invoiceFooter\",\n                \"alignment\": \"left\"\n            }\n        ],\n        \"margin\": [40, -20, 40, 0]\n    },\n    \"defaultStyle\": {\n        \"fontSize\": \"$fontSize\",\n        \"margin\": [8, 4, 8, 4]\n    },\n    \"styles\": {\n        \"primaryColor\":{\n            \"color\": \"$primaryColor:#AE1E54\"\n        },\n        \"accountName\": {\n            \"margin\": [4, 2, 4, 2],\n            \"color\": \"$primaryColor:#AE1E54\",\n            \"bold\": true\n        },\n        \"accountDetails\": {\n            \"margin\": [4, 2, 4, 2]\n        },\n        \"accountAddress\": {\n            \"margin\": [4, 2, 4, 2]\n        },\n        \"odd\": {\n            \"fillColor\":\"#F4F4F4\"\n        },\n        \"productKey\": {\n            \"bold\": true\n        },\n        \"subtotalsBalanceDueLabel\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"margin\": [320,20,0,0]\n        },\n        \"subtotalsBalanceDue\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"color\": \"$primaryColor:#AE1E54\",\n            \"bold\": true,\n            \"margin\":[0,-10,10,0],\n            \"alignment\": \"right\"\n        },\n        \"invoiceDetailBalanceDue\": {\n            \"bold\": true,\n            \"color\": \"$primaryColor:#AE1E54\"\n        },\n        \"invoiceDetailBalanceDueLabel\": {\n            \"bold\": true\n        },\n        \"tableHeader\": {\n            \"bold\": true,\n            \"color\": \"$primaryColor:#AE1E54\",\n            \"fontSize\": \"$fontSizeLargest\"\n        },\n        \"costTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"qtyTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"taxTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotalTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"clientName\": {\n            \"bold\": true\n        },\n        \"clientDetails\": {\n            \"margin\": [0,2,0,1]\n        },\n        \"header1\": {\n            \"bold\": true,\n            \"margin\": [0, 30, 0, 16],\n            \"fontSize\": 46\n        },\n        \"header2\": {\n            \"margin\": [0, 30, 0, 16],\n            \"fontSize\": 46,\n            \"italics\": true,\n            \"color\": \"$primaryColor:#AE1E54\"\n        },\n        \"invoiceLineItemsTable\": {\n            \"margin\": [0, 4, 0, 16]\n        },\n        \"cost\": {\n            \"alignment\": \"right\"\n        },\n        \"quantity\": {\n            \"alignment\": \"right\"\n        },\n        \"tax\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotal\": {\n            \"alignment\": \"right\"\n        },\n        \"subtotals\": {\n            \"alignment\": \"right\"\n        },\n        \"termsLabel\": {\n            \"bold\": true,\n            \"margin\": [0, 0, 0, 4]\n        },\n        \"header\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"bold\": true\n        },\n        \"subheader\": {\n            \"fontSize\": \"$fontSizeLarger\"\n        },\n        \"help\": {\n            \"fontSize\": \"$fontSizeSmaller\",\n            \"color\": \"#737373\"\n        }\n    },\n    \"pageMargins\": [40, 40, 40, 40]\n}\n'),(7,'Elegant',NULL,'{\n    \"content\": [\n    {\n        \"image\": \"$accountLogo\",\n        \"fit\": [120, 80],\n        \"alignment\": \"center\",\n        \"margin\": [0, 0, 0, 30]\n    },\n    {\"canvas\": [{ \"type\": \"line\", \"x1\": 0, \"y1\": 5, \"x2\": 515, \"y2\": 5, \"lineWidth\": 2}]},\n    {\"canvas\": [{ \"type\": \"line\", \"x1\": 0, \"y1\": 3, \"x2\": 515, \"y2\": 3, \"lineWidth\": 1}]},\n    {\n        \"columns\": [\n        {\n            \"width\": 120,\n            \"stack\": [\n                {\"text\": \"$invoiceToLabel\", \"style\": \"header\", \"margin\": [0, 0, 0, 6]},\n                \"$clientDetails\"\n            ]\n        },\n        {\n            \"width\": 10,\n            \"canvas\": [{ \"type\": \"line\", \"x1\": -2, \"y1\": 18, \"x2\": -2, \"y2\": 80, \"lineWidth\": 1,\"dash\": { \"length\": 2 }}]\n        },\n        {\n            \"width\": 120,\n            \"stack\": \"$accountDetails\",\n            \"margin\": [0, 20, 0, 0]\n        },\n        {\n            \"width\": 110,\n            \"stack\": \"$accountAddress\",\n            \"margin\": [0, 20, 0, 0]\n        },\n        {\n            \"stack\": [\n                {\"text\": \"$detailsLabel\", \"style\": \"header\", \"margin\": [0, 0, 0, 6]},\n                {\n                    \"width\":180,\n                    \"table\": {\n                        \"body\": \"$invoiceDetails\"\n                    },\n                    \"layout\": \"noBorders\"\n                }\n            ]\n        }],\n        \"margin\": [0, 20, 0, 0]\n    },\n    {\n        \"style\": \"invoiceLineItemsTable\",\n        \"table\": {\n            \"headerRows\": 1,\n            \"widths\": \"$invoiceLineItemColumns\",\n            \"body\": \"$invoiceLineItems\"\n        },\n        \"layout\": {\n            \"hLineWidth\": \"$notFirst:.5\",\n            \"vLineWidth\": \"$none\",\n            \"paddingLeft\": \"$amount:8\",\n            \"paddingRight\": \"$amount:8\",\n            \"paddingTop\": \"$amount:12\",\n            \"paddingBottom\": \"$amount:12\"\n        }\n    },\n    {\n        \"columns\": [\n        \"$notesAndTerms\",\n        {\n            \"style\": \"subtotals\",\n            \"table\": {\n                \"widths\": [\"*\", \"40%\"],\n                \"body\": \"$subtotalsWithoutBalance\"\n            },\n            \"layout\": {\n                \"hLineWidth\": \"$none\",\n                \"vLineWidth\": \"$none\",\n                \"paddingLeft\": \"$amount:34\",\n                \"paddingRight\": \"$amount:8\",\n                \"paddingTop\": \"$amount:4\",\n                \"paddingBottom\": \"$amount:4\"\n            }\n        }\n        ]\n    },\n    {\n        \"canvas\": [{ \"type\": \"line\", \"x1\": 270, \"y1\": 20, \"x2\": 515, \"y2\": 20, \"lineWidth\": 1,\"dash\": { \"length\": 2 }}]\n    },\n    {\n        \"text\": \"$balanceDueLabel\",\n        \"style\": \"subtotalsBalanceDueLabel\"\n    },\n    {\n        \"text\": \"$balanceDue\",\n        \"style\": \"subtotalsBalanceDue\"\n    },\n    {\n        \"canvas\": [{ \"type\": \"line\", \"x1\": 270, \"y1\": 20, \"x2\": 515, \"y2\": 20, \"lineWidth\": 1,\"dash\": { \"length\": 2 }}]\n    },\n    {\n        \"stack\": [\n            \"$invoiceDocuments\"\n        ],\n        \"style\": \"invoiceDocuments\"\n    }],\n    \"footer\": [\n    {\n        \"columns\": [\n            {\n                \"text\": \"$invoiceFooter\",\n                \"alignment\": \"left\"\n            }\n        ],\n        \"margin\": [40, -20, 40, 0]\n    },\n    {\"canvas\": [{ \"type\": \"line\", \"x1\": 35, \"y1\": 5, \"x2\": 555, \"y2\": 5, \"lineWidth\": 2,\"margin\": [30,0,0,0]}]},\n    {\"canvas\": [{ \"type\": \"line\", \"x1\": 35, \"y1\": 3, \"x2\": 555, \"y2\": 3, \"lineWidth\": 1,\"margin\": [30,0,0,0]}]}\n    ],\n    \"defaultStyle\": {\n        \"fontSize\": \"$fontSize\",\n        \"margin\": [8, 4, 8, 4]\n    },\n    \"styles\": {\n        \"accountDetails\": {\n            \"margin\": [0, 2, 0, 1]\n        },\n        \"clientDetails\": {\n            \"margin\": [0, 2, 0, 1]\n        },\n        \"accountAddress\": {\n            \"margin\": [0, 2, 0, 1]\n        },\n        \"clientName\": {\n            \"bold\": true\n        },\n        \"accountName\": {\n            \"bold\": true\n        },\n        \"odd\": {\n        },\n        \"subtotalsBalanceDueLabel\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"color\": \"$primaryColor:#5a7b61\",\n            \"margin\": [320,20,0,0]\n        },\n        \"subtotalsBalanceDue\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"color\": \"$primaryColor:#5a7b61\",\n            \"style\": true,\n            \"margin\":[0,-14,8,0],\n            \"alignment\":\"right\"\n        },\n        \"invoiceDetailBalanceDue\": {\n            \"color\": \"$primaryColor:#5a7b61\",\n            \"bold\": true\n        },\n        \"header\": {\n            \"fontSize\": 14,\n            \"bold\": true\n        },\n        \"tableHeader\": {\n            \"bold\": true,\n            \"color\": \"$primaryColor:#5a7b61\",\n            \"fontSize\": \"$fontSizeLargest\"\n        },\n        \"costTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"qtyTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"taxTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotalTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"invoiceLineItemsTable\": {\n            \"margin\": [0, 40, 0, 16]\n        },\n        \"cost\": {\n            \"alignment\": \"right\"\n        },\n        \"quantity\": {\n            \"alignment\": \"right\"\n        },\n        \"tax\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotal\": {\n            \"alignment\": \"right\"\n        },\n        \"subtotals\": {\n            \"alignment\": \"right\"\n        },\n        \"termsLabel\": {\n            \"bold\": true,\n            \"margin\": [0, 0, 0, 4]\n        },\n        \"header\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"bold\": true\n        },\n        \"subheader\": {\n            \"fontSize\": \"$fontSizeLarger\"\n        },\n        \"help\": {\n            \"fontSize\": \"$fontSizeSmaller\",\n            \"color\": \"#737373\"\n        }\n    },\n    \"pageMargins\": [40, 40, 40, 40]\n}\n'),(8,'Hipster',NULL,'{\n    \"content\": [\n    {\n        \"columns\": [\n		{\n			\"width\":10,\n			\"canvas\": [{ \"type\": \"line\", \"x1\": 0, \"y1\": 0, \"x2\": 0, \"y2\": 75, \"lineWidth\": 0.5}]\n		},\n        {\n			\"width\":120,\n            \"stack\": [\n                {\"text\": \"$fromLabelUC\", \"style\": \"fromLabel\"}, \n                \"$accountDetails\" \n            ]\n        },\n        {\n			\"width\":120,\n            \"stack\": [\n                {\"text\": \" \"},\n                \"$accountAddress\"\n            ],\n			\"margin\": [10, 0, 0, 16]\n        },\n		{\n			\"width\":10,\n			\"canvas\": [{ \"type\": \"line\", \"x1\": 0, \"y1\": 0, \"x2\": 0, \"y2\": 75, \"lineWidth\": 0.5}]\n		},\n		{\n			\"stack\": [\n                {\"text\": \"$toLabelUC\", \"style\": \"toLabel\"}, \n                \"$clientDetails\"\n            ]\n		},\n		[\n            {\n                \"image\": \"$accountLogo\",\n                \"fit\": [120, 80]\n            }\n        ]\n        ]\n    },\n    {\n        \"text\": \"$entityTypeUC\",\n        \"margin\": [0, 4, 0, 8],\n        \"bold\": \"true\",\n        \"fontSize\": 42\n    },\n	{\n        \"columnGap\": 16,\n		\"columns\": [\n			{\n				\"width\":\"auto\",\n				\"text\": [\"$invoiceNoLabel\",\" \",\"$invoiceNumberValue\"],\n				\"bold\": true,\n				\"color\":\"$primaryColor:#bc9f2b\",\n				\"fontSize\":10\n			},\n			{\n				\"width\":\"auto\",\n				\"text\": [\"$invoiceDateLabel\",\" \",\"$invoiceDateValue\"],\n				\"fontSize\":10\n			},\n			{\n				\"width\":\"auto\",\n				\"text\": [\"$dueDateLabel?\",\" \",\"$dueDateValue\"],\n				\"fontSize\":10\n			},\n			{\n				\"width\":\"*\",\n				\"text\": [\"$balanceDueLabel\",\" \",{\"text\":\"$balanceDue\", \"bold\":true, \"color\":\"$primaryColor:#bc9f2b\"}],\n				\"fontSize\":10\n			}\n		]\n	},\n    {\n		\"margin\": [0, 26, 0, 0],\n        \"table\": {\n            \"headerRows\": 1,\n            \"widths\": \"$invoiceLineItemColumns\",\n            \"body\": \"$invoiceLineItems\"\n        },\n        \"layout\": {\n            \"hLineWidth\": \"$none\",\n            \"vLineWidth\": \"$amount:.5\",\n            \"paddingLeft\": \"$amount:8\", \n            \"paddingRight\": \"$amount:8\", \n            \"paddingTop\": \"$amount:8\", \n            \"paddingBottom\": \"$amount:8\"            \n        }\n    },\n    {\n        \"columns\": [\n        {\n            \"stack\": \"$notesAndTerms\",\n            \"width\": \"*\",\n            \"margin\": [0, 12, 0, 0]\n        },\n        {\n            \"width\": 200,\n            \"style\": \"subtotals\",\n            \"table\": {\n                \"widths\": [\"*\", \"36%\"],\n                \"body\": \"$subtotals\"\n            },\n            \"layout\": {\n                \"hLineWidth\": \"$none\",\n                \"vLineWidth\": \"$notFirst:.5\",\n                \"paddingLeft\": \"$amount:8\", \n                \"paddingRight\": \"$amount:8\", \n                \"paddingTop\": \"$amount:12\", \n                \"paddingBottom\": \"$amount:4\"            \n            }\n        }\n        ]\n    },\n    {\n        \"stack\": [\n            \"$invoiceDocuments\"\n        ],\n        \"style\": \"invoiceDocuments\"\n    }\n    ],\n    \"footer\": {\n        \"columns\": [\n            {\n                \"text\": \"$invoiceFooter\",\n                \"alignment\": \"left\"\n            }\n        ],\n        \"margin\": [40, -20, 40, 0]\n    },\n    \"defaultStyle\": {\n        \"fontSize\": \"$fontSize\",\n        \"margin\": [8, 4, 8, 4]\n    },\n    \"styles\": {\n        \"accountName\": {\n            \"bold\": true\n        },\n        \"clientName\": {\n            \"bold\": true\n        },\n        \"subtotalsBalanceDueLabel\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"bold\": true\n        },\n        \"subtotalsBalanceDue\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"color\": \"$primaryColor:#bc9f2b\",\n            \"bold\": true\n        },\n        \"tableHeader\": {\n            \"bold\": true,\n            \"fontSize\": \"$fontSizeLargest\"\n        },\n        \"costTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"qtyTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"taxTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotalTableHeader\": {\n            \"alignment\": \"right\"\n        },        \n        \"fromLabel\": {\n            \"color\": \"$primaryColor:#bc9f2b\",\n            \"bold\": true  \n        },\n        \"toLabel\": {\n            \"color\": \"$primaryColor:#bc9f2b\",\n            \"bold\": true  \n        },\n        \"accountDetails\": {\n            \"margin\": [0, 2, 0, 1]\n        },\n        \"accountAddress\": {\n            \"margin\": [0, 2, 0, 1]\n        },\n        \"clientDetails\": {\n            \"margin\": [0, 2, 0, 1]\n        },\n        \"cost\": {\n            \"alignment\": \"right\"\n        },\n        \"quantity\": {\n            \"alignment\": \"right\"\n        },\n        \"tax\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotal\": {\n            \"alignment\": \"right\"\n        },\n        \"subtotals\": {\n            \"alignment\": \"right\"\n        },            \n        \"termsLabel\": {\n            \"bold\": true,\n            \"margin\": [0, 16, 0, 4]\n        },\n        \"header\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"bold\": true\n        },\n        \"subheader\": {\n            \"fontSize\": \"$fontSizeLarger\"\n        },\n        \"help\": {\n            \"fontSize\": \"$fontSizeSmaller\",\n            \"color\": \"#737373\"\n        }\n    },\n    \"pageMargins\": [40, 40, 40, 40]\n}'),(9,'Playful',NULL,'{\n    \"content\": [\n    {\n        \"columns\": [\n		{\n			\"image\": \"$accountLogo\",\n			\"fit\": [120, 80]\n		},\n		{\"canvas\": [{ \"type\": \"rect\", \"x\": 0, \"y\": 0, \"w\": 190, \"h\": \"$invoiceDetailsHeight\",\"r\":5, \"lineWidth\": 1,\"color\":\"$primaryColor:#009d91\"}],\"width\":10,\"margin\":[200,0,0,0]},\n		{\n			\"width\":400,\n            \"table\": { \n                \"body\": \"$invoiceDetails\"\n            },\n            \"layout\": \"noBorders\",\n			\"margin\": [210, 10, 10, 0]\n		}\n        ] \n    },\n	{\n        \"margin\": [0, 18, 0, 0],\n        \"columnGap\": 50,\n		\"columns\": [\n			{\n				\"width\": 212,\n				\"stack\": [\n                    {\"text\": \"$invoiceToLabel:\", \"style\": \"toLabel\"},\n                    {\n                        \"canvas\": [{ \"type\": \"line\", \"x1\": 0, \"y1\": 4, \"x2\": 150, \"y2\": 4, \"lineWidth\": 1,\"dash\": { \"length\": 3 },\"lineColor\":\"$primaryColor:#009d91\"}],\n                        \"margin\": [0, 0, 0, 4]\n                    },\n                    \"$clientDetails\",\n                    {\"canvas\": [{ \"type\": \"line\", \"x1\": 0, \"y1\": 9, \"x2\": 150, \"y2\": 9, \"lineWidth\": 1,\"dash\": { \"length\": 3 },\"lineColor\":\"$primaryColor:#009d91\"}]}\n                ]\n			},\n			{\n                \"width\": \"*\",\n				\"stack\": [\n                    {\"text\": \"$fromLabel:\", \"style\": \"fromLabel\"},\n                    {\n                        \"canvas\": [{ \"type\": \"line\", \"x1\": 0, \"y1\": 4, \"x2\": 250, \"y2\": 4, \"lineWidth\": 1,\"dash\": { \"length\": 3 },\"lineColor\":\"$primaryColor:#009d91\"}],\n                        \"margin\": [0, 0, 0, 4]\n                    },\n                    {\"columns\":[\n                        \"$accountDetails\",\n                        \"$accountAddress\"    \n                    ], \"columnGap\": 4},                    \n                    {\"canvas\": [{ \"type\": \"line\", \"x1\": 0, \"y1\": 9, \"x2\": 250, \"y2\": 9, \"lineWidth\": 1,\"dash\": { \"length\": 3 },\"lineColor\":\"$primaryColor:#009d91\"}]}\n                ]\n			}\n		]\n	},\n	{\"canvas\": [{ \"type\": \"rect\", \"x\": 0, \"y\": 0, \"w\": 515, \"h\": 35,\"r\":6, \"lineWidth\": 1,\"color\":\"$primaryColor:#009d91\"}],\"width\":10,\"margin\":[0,30,0,-30]},\n    {\n        \"style\": \"invoiceLineItemsTable\",\n        \"table\": {\n            \"headerRows\": 1,\n            \"widths\": \"$invoiceLineItemColumns\",\n            \"body\": \"$invoiceLineItems\"\n        },\n        \"layout\": {\n            \"hLineWidth\": \"$notFirst:.5\",\n            \"vLineWidth\": \"$none\",\n            \"hLineColor\": \"$primaryColor:#009d91\",\n            \"paddingLeft\": \"$amount:8\", \n            \"paddingRight\": \"$amount:8\", \n            \"paddingTop\": \"$amount:8\", \n            \"paddingBottom\": \"$amount:8\"\n        }\n    },    \n    {\n    \"columns\": [\n      \"$notesAndTerms\",\n      {\n        \"stack\": [\n          {\n            \"style\": \"subtotals\",\n            \"table\": {\n              \"widths\": [\"*\", \"35%\"],\n              \"body\": \"$subtotalsWithoutBalance\"\n            },\n            \"layout\": {\n              \"hLineWidth\": \"$none\",\n              \"vLineWidth\": \"$none\",\n              \"paddingLeft\": \"$amount:34\",\n              \"paddingRight\": \"$amount:8\",\n              \"paddingTop\": \"$amount:4\",\n              \"paddingBottom\": \"$amount:4\"\n            }\n          },\n        {\n          \"canvas\": [\n          {\n            \"type\": \"rect\",\n            \"x\": 76,\n            \"y\": 20,\n            \"w\": 182,\n            \"h\": 30,\n            \"r\": 4,\n            \"lineWidth\": 1,\n            \"color\": \"$primaryColor:#009d91\"\n          }\n        ]\n        },\n          {\n            \"style\": \"subtotalsBalance\",\n            \"table\": {\n                \"widths\": [\"*\", \"35%\"],\n                \"body\": \"$subtotalsBalance\"\n            },\n            \"layout\": {\n              \"hLineWidth\": \"$none\",\n              \"vLineWidth\": \"$none\",\n              \"paddingLeft\": \"$amount:34\",\n              \"paddingRight\": \"$amount:8\",\n              \"paddingTop\": \"$amount:4\",\n              \"paddingBottom\": \"$amount:4\"\n            }\n          }\n        ]\n      }\n    ]\n  },    \n    {\n        \"stack\": [\n            \"$invoiceDocuments\"\n        ],\n        \"style\": \"invoiceDocuments\"\n    }\n],    \n    \"footer\": [\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 0, \"y1\": 38, \"x2\": 68, \"y2\": 38, \"lineWidth\": 6,\"lineColor\":\"#009d91\"}]},\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 68, \"y1\": 0, \"x2\": 135, \"y2\": 0, \"lineWidth\": 6,\"lineColor\":\"#1d766f\"}]},\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 135, \"y1\": 0, \"x2\": 201, \"y2\": 0, \"lineWidth\": 6,\"lineColor\":\"#ffb800\"}]},\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 201, \"y1\": 0, \"x2\": 267, \"y2\": 0, \"lineWidth\": 6,\"lineColor\":\"#bf9730\"}]},\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 267, \"y1\": 0, \"x2\": 333, \"y2\": 0, \"lineWidth\": 6,\"lineColor\":\"#ac2b50\"}]},\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 333, \"y1\": 0, \"x2\": 399, \"y2\": 0, \"lineWidth\": 6,\"lineColor\":\"#e60042\"}]},\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 399, \"y1\": 0, \"x2\": 465, \"y2\": 0, \"lineWidth\": 6,\"lineColor\":\"#ffb800\"}]},\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 465, \"y1\": 0, \"x2\": 532, \"y2\": 0, \"lineWidth\": 6,\"lineColor\":\"#009d91\"}]},\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 532, \"y1\": 0, \"x2\": 600, \"y2\": 0, \"lineWidth\": 6,\"lineColor\":\"#ac2b50\"}]},\n        {\n            \"text\": \"$invoiceFooter\",\n            \"alignment\": \"left\",\n            \"margin\": [40, -60, 40, 0]\n        }\n    ],\n    \"header\": [\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 0, \"y1\": 0, \"x2\": 68, \"y2\": 0, \"lineWidth\": 9,\"lineColor\":\"#009d91\"}]},\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 68, \"y1\": 0, \"x2\": 135, \"y2\": 0, \"lineWidth\": 9,\"lineColor\":\"#1d766f\"}]},\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 135, \"y1\": 0, \"x2\": 201, \"y2\": 0, \"lineWidth\": 9,\"lineColor\":\"#ffb800\"}]},\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 201, \"y1\": 0, \"x2\": 267, \"y2\": 0, \"lineWidth\": 9,\"lineColor\":\"#bf9730\"}]},\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 267, \"y1\": 0, \"x2\": 333, \"y2\": 0, \"lineWidth\": 9,\"lineColor\":\"#ac2b50\"}]},\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 333, \"y1\": 0, \"x2\": 399, \"y2\": 0, \"lineWidth\": 9,\"lineColor\":\"#e60042\"}]},\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 399, \"y1\": 0, \"x2\": 465, \"y2\": 0, \"lineWidth\": 9,\"lineColor\":\"#ffb800\"}]},\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 465, \"y1\": 0, \"x2\": 532, \"y2\": 0, \"lineWidth\": 9,\"lineColor\":\"#009d91\"}]},\n        {\"canvas\": [{ \"type\": \"line\", \"x1\": 532, \"y1\": 0, \"x2\": 600, \"y2\": 0, \"lineWidth\": 9,\"lineColor\":\"#ac2b50\"}]}\n    ],\n    \"defaultStyle\": {\n        \"fontSize\": \"$fontSize\",\n        \"margin\": [8, 4, 8, 4]\n    },\n    \"styles\": {\n        \"accountName\": {\n            \"color\": \"$secondaryColor:#bb3328\"\n        },\n        \"accountDetails\": {\n            \"margin\": [0, 2, 0, 1]\n        },\n        \"accountAddress\": {\n            \"margin\": [0, 2, 0, 1]\n        },\n        \"clientDetails\": {\n            \"margin\": [0, 2, 0, 1]\n        },\n        \"clientName\": {\n            \"color\": \"$secondaryColor:#bb3328\"\n        },\n        \"even\": {\n			\"fillColor\":\"#E8E8E8\"\n        },\n        \"odd\": {\n            \"fillColor\":\"#F7F7F7\"\n        },\n        \"productKey\": {\n            \"color\": \"$secondaryColor:#bb3328\"\n        },\n        \"lineTotal\": {\n            \"bold\": true\n        },\n        \"tableHeader\": {\n            \"bold\": true,\n            \"fontSize\": \"$fontSizeLargest\",\n            \"color\": \"#FFFFFF\"\n        },\n        \"costTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"qtyTableHeader\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotalTableHeader\": {\n            \"alignment\": \"right\"\n        },        \n        \"subtotalsBalanceDueLabel\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"color\":\"#FFFFFF\",\n            \"bold\": true\n        },\n        \"subtotalsBalanceDue\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"bold\": true,\n            \"color\":\"#FFFFFF\",\n            \"alignment\":\"right\"\n        },\n        \"invoiceDetails\": {\n            \"color\": \"#FFFFFF\"\n        },\n        \"invoiceLineItemsTable\": {\n            \"margin\": [0, 0, 0, 16]\n        },\n        \"invoiceDetailBalanceDueLabel\": {\n            \"bold\": true\n        },\n        \"invoiceDetailBalanceDue\": {\n            \"bold\": true\n        },\n        \"fromLabel\": {\n            \"color\": \"$primaryColor:#009d91\"\n        },\n        \"toLabel\": {\n            \"color\": \"$primaryColor:#009d91\"\n        },\n        \"cost\": {\n            \"alignment\": \"right\"\n        },\n        \"quantity\": {\n            \"alignment\": \"right\"\n        },\n        \"tax\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotal\": {\n            \"alignment\": \"right\"\n        },\n        \"subtotals\": {\n            \"alignment\": \"right\"\n        },            \n        \"subtotalsBalance\": {\n            \"alignment\": \"right\",\n            \"margin\": [0, -25, 0, 0]\n        },            \n        \"termsLabel\": {\n            \"bold\": true,\n            \"margin\": [0, 0, 0, 4]\n        },\n        \"header\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"bold\": true\n        },\n        \"subheader\": {\n            \"fontSize\": \"$fontSizeLarger\"\n        },\n        \"help\": {\n            \"fontSize\": \"$fontSizeSmaller\",\n            \"color\": \"#737373\"\n        }\n    },\n    \"pageMargins\": [40, 40, 40, 40]\n}'),(10,'Photo',NULL,'{\n    \"content\": [\n    {\n        \"columns\": [\n        {\n            \"image\": \"$accountLogo\",\n            \"fit\": [120, 80]\n        },\n        {\n            \"text\": \"\",\n            \"width\": \"*\"\n        },\n        {\n            \"width\":180,\n            \"table\": { \n                \"body\": \"$invoiceDetails\"\n            },\n            \"layout\": \"noBorders\"\n        }]\n    },\n    {\n        \"image\": \"data:image/jpeg;base64,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\",\n        \"margin\": [-40, 16, 0, 0],\n        \"width\": 595\n    },\n    {\n        \"margin\": [-20, -150, 0, 0],\n        \"columnGap\": 8,\n        \"columns\": [\n            {\n                \"width\": \"auto\",\n                \"text\": \"$toLabel:\",\n                \"style\": \"bold\",\n                \"color\":\"#cd5138\"\n            },\n            {\n                \"width\": \"*\",\n                \"stack\": \"$clientDetails\",\n                \"margin\": [4, 0, 0, 0]\n            }\n        ]\n    },\n    {\n        \"margin\": [-20, 10, 0, 140],\n        \"columnGap\": 8,\n        \"columns\": [\n            {\n                \"width\": \"auto\",\n                \"text\": \"$fromLabel:\",\n                \"style\": \"bold\",\n                \"color\":\"#cd5138\"\n            },\n            {\n                \"width\": \"*\",\n                \"stack\": [\n                    {\n                        \"width\": 150,\n                        \"stack\": \"$accountDetails\"\n                    },\n                    {\n                        \"width\": 150,\n                        \"stack\": \"$accountAddress\"\n                    }\n                ]\n            }\n        ]\n    },\n    {\"canvas\": [{ \"type\": \"line\", \"x1\": 0, \"y1\": 5, \"x2\": 515, \"y2\": 5, \"lineWidth\": 1.5}],\"margin\":[0,0,0,-30]},\n    {\n        \"style\": \"invoiceLineItemsTable\",\n        \"table\": {\n            \"headerRows\": 1,\n            \"widths\": \"$invoiceLineItemColumns\",\n            \"body\": \"$invoiceLineItems\"\n        },\n        \"layout\": {\n            \"hLineWidth\": \"$notFirst:.5\",\n            \"vLineWidth\": \"$none\",\n            \"hLineColor\": \"#000000\",\n            \"paddingLeft\": \"$amount:8\", \n            \"paddingRight\": \"$amount:8\", \n            \"paddingTop\": \"$amount:10\", \n            \"paddingBottom\": \"$amount:10\" \n        }\n    },\n    {\n        \"columns\": [\n        \"$notesAndTerms\",\n        {\n            \"alignment\": \"right\",\n            \"table\": {\n                \"widths\": [\"*\", \"40%\"],\n                \"body\": \"$subtotals\"\n            },\n            \"layout\": {\n                \"hLineWidth\": \"$none\",\n                \"vLineWidth\": \"$none\",\n                \"paddingLeft\": \"$amount:34\", \n                \"paddingRight\": \"$amount:8\", \n                \"paddingTop\": \"$amount:4\", \n                \"paddingBottom\": \"$amount:4\"            \n            }\n        }]\n    },\n    {\n        \"stack\": [\n            \"$invoiceDocuments\"\n        ],\n        \"style\": \"invoiceDocuments\"\n    }\n    ],\n    \"defaultStyle\": {\n        \"fontSize\": \"$fontSize\",\n        \"margin\": [8, 4, 8, 4]\n    },\n    \"footer\": {\n        \"columns\": [\n            {\n                \"text\": \"$invoiceFooter\",\n                \"alignment\": \"left\"\n            }\n        ],\n        \"margin\": [40, -20, 40, 0]\n    },\n    \"styles\": {\n        \"accountDetails\": {\n            \"margin\": [0, 0, 0, 3]\n        },\n        \"accountAddress\": {\n            \"margin\": [0, 0, 0, 3]\n        },\n        \"clientDetails\": {\n            \"margin\": [0, 0, 0, 3]\n        },\n        \"productKey\": {\n            \"color\": \"$primaryColor:#cd5138\"\n        },\n        \"lineTotal\": {\n            \"color\": \"$primaryColor:#cd5138\"\n        },\n        \"tableHeader\": {\n            \"bold\": true,\n            \"fontSize\": \"$fontSizeLarger\"\n        },\n        \"subtotalsBalanceDueLabel\": {\n            \"fontSize\": \"$fontSizeLargest\"\n        },\n        \"subtotalsBalanceDue\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"color\": \"$primaryColor:#cd5138\"\n        },\n        \"invoiceLineItemsTable\": {\n            \"margin\": [0, 0, 0, 16]\n        },\n        \"cost\": {\n            \"alignment\": \"right\"\n        },\n        \"quantity\": {\n            \"alignment\": \"right\"\n        },\n        \"tax\": {\n            \"alignment\": \"right\"\n        },\n        \"lineTotal\": {\n            \"alignment\": \"right\"\n        },\n        \"termsLabel\": {\n            \"bold\": true,\n            \"margin\": [0, 0, 0, 4]\n        },\n        \"header\": {\n            \"fontSize\": \"$fontSizeLargest\",\n            \"bold\": true\n        },\n        \"help\": {\n            \"fontSize\": \"$fontSizeSmaller\",\n            \"color\": \"#737373\"\n        }\n    },\n    \"pageMargins\": [40, 30, 40, 30]\n}\n'),(11,'Custom1',NULL,NULL),(12,'Custom2',NULL,NULL),(13,'Custom3',NULL,NULL);
/*!40000 ALTER TABLE `invoice_designs` ENABLE KEYS */;
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `product_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `notes` text COLLATE utf8_unicode_ci NOT NULL,
  `cost` decimal(13,2) NOT NULL,
  `qty` decimal(13,2) DEFAULT NULL,
  `tax_name1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tax_rate1` decimal(13,3) DEFAULT NULL,
  `public_id` int(10) unsigned NOT NULL,
  `custom_value1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `custom_value2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tax_name2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tax_rate2` decimal(13,3) NOT NULL,
  `invoice_item_type_id` smallint(6) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_items_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `invoice_items_product_id_foreign` (`product_id`),
  KEY `invoice_items_user_id_foreign` (`user_id`),
  KEY `invoice_items_invoice_id_index` (`invoice_id`),
  CONSTRAINT `invoice_items_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_items_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoice_items_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `invoice_statuses`
--

LOCK TABLES `invoice_statuses` WRITE;
/*!40000 ALTER TABLE `invoice_statuses` DISABLE KEYS */;
INSERT INTO `invoice_statuses` VALUES (1,'Draft'),(2,'Sent'),(3,'Viewed'),(4,'Approved'),(5,'Partial'),(6,'Paid');
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `invoice_number` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `discount` double(8,2) NOT NULL,
  `po_number` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `invoice_date` date DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `terms` text COLLATE utf8_unicode_ci NOT NULL,
  `public_notes` text COLLATE utf8_unicode_ci NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `is_recurring` tinyint(1) NOT NULL DEFAULT '0',
  `frequency_id` int(10) unsigned NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `last_sent_date` date DEFAULT NULL,
  `recurring_invoice_id` int(10) unsigned DEFAULT NULL,
  `tax_name1` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `tax_rate1` decimal(13,3) NOT NULL,
  `amount` decimal(13,2) NOT NULL,
  `balance` decimal(13,2) NOT NULL,
  `public_id` int(10) unsigned NOT NULL,
  `invoice_design_id` int(10) unsigned NOT NULL DEFAULT '1',
  `invoice_type_id` tinyint(1) NOT NULL DEFAULT '0',
  `quote_id` int(10) unsigned DEFAULT NULL,
  `quote_invoice_id` int(10) unsigned DEFAULT NULL,
  `custom_value1` decimal(13,2) NOT NULL DEFAULT '0.00',
  `custom_value2` decimal(13,2) NOT NULL DEFAULT '0.00',
  `custom_taxes1` tinyint(1) NOT NULL DEFAULT '0',
  `custom_taxes2` tinyint(1) NOT NULL DEFAULT '0',
  `is_amount_discount` tinyint(1) DEFAULT NULL,
  `invoice_footer` text COLLATE utf8_unicode_ci,
  `partial` decimal(13,2) DEFAULT NULL,
  `has_tasks` tinyint(1) NOT NULL DEFAULT '0',
  `auto_bill` tinyint(1) NOT NULL DEFAULT '0',
  `custom_text_value1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `custom_text_value2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `has_expenses` tinyint(1) NOT NULL DEFAULT '0',
  `tax_name2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tax_rate2` decimal(13,3) NOT NULL,
  `client_enable_auto_bill` tinyint(1) NOT NULL DEFAULT '0',
  `is_public` tinyint(1) NOT NULL DEFAULT '0',
  `private_notes` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoices_account_id_public_id_unique` (`account_id`,`public_id`),
  UNIQUE KEY `invoices_account_id_invoice_number_unique` (`account_id`,`invoice_number`),
  KEY `invoices_user_id_foreign` (`user_id`),
  KEY `invoices_invoice_status_id_foreign` (`invoice_status_id`),
  KEY `invoices_client_id_index` (`client_id`),
  KEY `invoices_account_id_index` (`account_id`),
  KEY `invoices_recurring_invoice_id_index` (`recurring_invoice_id`),
  KEY `invoices_public_id_index` (`public_id`),
  KEY `invoices_invoice_design_id_foreign` (`invoice_design_id`),
  CONSTRAINT `invoices_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoices_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `invoices_invoice_design_id_foreign` FOREIGN KEY (`invoice_design_id`) REFERENCES `invoice_designs` (`id`),
  CONSTRAINT `invoices_invoice_status_id_foreign` FOREIGN KEY (`invoice_status_id`) REFERENCES `invoice_statuses` (`id`),
  CONSTRAINT `invoices_recurring_invoice_id_foreign` FOREIGN KEY (`recurring_invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
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
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8_unicode_ci NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_reserved_reserved_at_index` (`queue`,`reserved`,`reserved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `jobs`
--

LOCK TABLES `jobs` WRITE;
/*!40000 ALTER TABLE `jobs` DISABLE KEYS */;
/*!40000 ALTER TABLE `jobs` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `languages`
--

DROP TABLE IF EXISTS `languages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `languages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `locale` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `languages`
--

LOCK TABLES `languages` WRITE;
/*!40000 ALTER TABLE `languages` DISABLE KEYS */;
INSERT INTO `languages` VALUES (1,'English','en'),(2,'Italian','it'),(3,'German','de'),(4,'French','fr'),(5,'Portuguese - Brazilian','pt_BR'),(6,'Dutch','nl'),(7,'Spanish','es'),(8,'Norwegian','nb_NO'),(9,'Danish','da'),(10,'Japanese','ja'),(11,'Swedish','sv'),(12,'Spanish - Spain','es_ES'),(13,'French - Canada','fr_CA'),(14,'Lithuanian','lt'),(15,'Polish','pl'),(16,'Czech','cs'),(17,'Croatian','hr'),(18,'Albanian','sq'),(19,'Greek','el'),(20,'English - United Kingdom','en_UK'),(21,'Portuguese - Portugal','pt_PT'),(22,'Slovenian','sl'),(23,'Finnish','fi'),(24,'Romanian','ro'),(25,'Turkish - Turkey','tr_TR');
/*!40000 ALTER TABLE `languages` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `licenses`
--

DROP TABLE IF EXISTS `licenses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `licenses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `affiliate_id` int(10) unsigned NOT NULL,
  `first_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `last_name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `license_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `is_claimed` tinyint(1) NOT NULL,
  `transaction_reference` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `product_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `licenses_license_key_unique` (`license_key`),
  KEY `licenses_affiliate_id_foreign` (`affiliate_id`),
  CONSTRAINT `licenses_affiliate_id_foreign` FOREIGN KEY (`affiliate_id`) REFERENCES `affiliates` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `licenses`
--

LOCK TABLES `licenses` WRITE;
/*!40000 ALTER TABLE `licenses` DISABLE KEYS */;
/*!40000 ALTER TABLE `licenses` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lookup_account_tokens`
--

DROP TABLE IF EXISTS `lookup_account_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lookup_account_tokens` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lookup_account_id` int(10) unsigned NOT NULL,
  `token` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lookup_tokens_token_unique` (`token`),
  KEY `lookup_tokens_lookup_account_id_index` (`lookup_account_id`),
  CONSTRAINT `lookup_tokens_lookup_account_id_foreign` FOREIGN KEY (`lookup_account_id`) REFERENCES `lookup_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lookup_account_tokens`
--

LOCK TABLES `lookup_account_tokens` WRITE;
/*!40000 ALTER TABLE `lookup_account_tokens` DISABLE KEYS */;
/*!40000 ALTER TABLE `lookup_account_tokens` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lookup_accounts`
--

DROP TABLE IF EXISTS `lookup_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lookup_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lookup_company_id` int(10) unsigned NOT NULL,
  `account_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lookup_accounts_account_key_unique` (`account_key`),
  KEY `lookup_accounts_lookup_company_id_index` (`lookup_company_id`),
  CONSTRAINT `lookup_accounts_lookup_company_id_foreign` FOREIGN KEY (`lookup_company_id`) REFERENCES `lookup_companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lookup_accounts`
--

LOCK TABLES `lookup_accounts` WRITE;
/*!40000 ALTER TABLE `lookup_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `lookup_accounts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lookup_companies`
--

DROP TABLE IF EXISTS `lookup_companies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lookup_companies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `db_server_id` int(10) unsigned NOT NULL,
  `company_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lookup_companies_db_server_id_company_id_unique` (`db_server_id`,`company_id`),
  KEY `lookup_companies_company_id_index` (`company_id`),
  CONSTRAINT `lookup_companies_db_server_id_foreign` FOREIGN KEY (`db_server_id`) REFERENCES `db_servers` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lookup_companies`
--

LOCK TABLES `lookup_companies` WRITE;
/*!40000 ALTER TABLE `lookup_companies` DISABLE KEYS */;
/*!40000 ALTER TABLE `lookup_companies` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lookup_contacts`
--

DROP TABLE IF EXISTS `lookup_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lookup_contacts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lookup_account_id` int(10) unsigned NOT NULL,
  `contact_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lookup_contacts_contact_key_unique` (`contact_key`),
  KEY `lookup_contacts_lookup_account_id_index` (`lookup_account_id`),
  CONSTRAINT `lookup_contacts_lookup_account_id_foreign` FOREIGN KEY (`lookup_account_id`) REFERENCES `lookup_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lookup_contacts`
--

LOCK TABLES `lookup_contacts` WRITE;
/*!40000 ALTER TABLE `lookup_contacts` DISABLE KEYS */;
/*!40000 ALTER TABLE `lookup_contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lookup_invitations`
--

DROP TABLE IF EXISTS `lookup_invitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lookup_invitations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lookup_account_id` int(10) unsigned NOT NULL,
  `invitation_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `message_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lookup_invitations_invitation_key_unique` (`invitation_key`),
  UNIQUE KEY `lookup_invitations_message_id_unique` (`message_id`),
  KEY `lookup_invitations_lookup_account_id_index` (`lookup_account_id`),
  CONSTRAINT `lookup_invitations_lookup_account_id_foreign` FOREIGN KEY (`lookup_account_id`) REFERENCES `lookup_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lookup_invitations`
--

LOCK TABLES `lookup_invitations` WRITE;
/*!40000 ALTER TABLE `lookup_invitations` DISABLE KEYS */;
/*!40000 ALTER TABLE `lookup_invitations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `lookup_users`
--

DROP TABLE IF EXISTS `lookup_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lookup_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `lookup_account_id` int(10) unsigned NOT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `confirmation_code` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `oauth_user_key` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `referral_code` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `lookup_users_lookup_account_id_user_id_unique` (`lookup_account_id`,`user_id`),
  UNIQUE KEY `lookup_users_email_unique` (`email`),
  UNIQUE KEY `lookup_users_confirmation_code_unique` (`confirmation_code`),
  UNIQUE KEY `lookup_users_oauth_user_key_unique` (`oauth_user_key`),
  UNIQUE KEY `lookup_users_referral_code_unique` (`referral_code`),
  KEY `lookup_users_lookup_account_id_index` (`lookup_account_id`),
  KEY `lookup_users_user_id_index` (`user_id`),
  CONSTRAINT `lookup_users_lookup_account_id_foreign` FOREIGN KEY (`lookup_account_id`) REFERENCES `lookup_accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `lookup_users`
--

LOCK TABLES `lookup_users` WRITE;
/*!40000 ALTER TABLE `lookup_users` DISABLE KEYS */;
/*!40000 ALTER TABLE `lookup_users` ENABLE KEYS */;
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
INSERT INTO `migrations` VALUES ('2013_11_05_180133_confide_setup_users_table',1),('2013_11_28_195703_setup_countries_table',1),('2014_02_13_151500_add_cascase_drops',1),('2014_02_19_151817_add_support_for_invoice_designs',1),('2014_03_03_155556_add_phone_to_account',1),('2014_03_19_201454_add_language_support',1),('2014_03_20_200300_create_payment_libraries',1),('2014_03_23_051736_enable_forcing_jspdf',1),('2014_03_25_102200_add_sort_and_recommended_to_gateways',1),('2014_04_03_191105_add_pro_plan',1),('2014_04_17_100523_add_remember_token',1),('2014_04_17_145108_add_custom_fields',1),('2014_04_23_170909_add_products_settings',1),('2014_04_29_174315_add_advanced_settings',1),('2014_05_17_175626_add_quotes',1),('2014_06_17_131940_add_accepted_credit_cards_to_account_gateways',1),('2014_07_13_142654_one_click_install',1),('2014_07_17_205900_support_hiding_quantity',1),('2014_07_24_171214_add_zapier_support',1),('2014_10_01_141248_add_company_vat_number',1),('2014_10_05_141856_track_last_seen_message',1),('2014_10_06_103529_add_timesheets',1),('2014_10_06_195330_add_invoice_design_table',1),('2014_10_13_054100_add_invoice_number_settings',1),('2014_10_14_225227_add_danish_translation',1),('2014_10_22_174452_add_affiliate_price',1),('2014_10_30_184126_add_company_id_number',1),('2014_11_04_200406_allow_null_client_currency',1),('2014_12_03_154119_add_discount_type',1),('2015_02_12_102940_add_email_templates',1),('2015_02_17_131714_support_token_billing',1),('2015_02_27_081836_add_invoice_footer',1),('2015_03_03_140259_add_tokens',1),('2015_03_09_151011_add_ip_to_activity',1),('2015_03_15_174122_add_pdf_email_attachment_option',1),('2015_03_30_100000_create_password_resets_table',1),('2015_04_12_093447_add_sv_language',1),('2015_04_13_100333_add_notify_approved',1),('2015_04_16_122647_add_partial_amount_to_invoices',1),('2015_05_21_184104_add_font_size',1),('2015_05_27_121828_add_tasks',1),('2015_05_27_170808_add_custom_invoice_labels',1),('2015_06_09_134208_add_has_tasks_to_invoices',1),('2015_06_14_093410_enable_resuming_tasks',1),('2015_06_14_173025_multi_company_support',1),('2015_07_07_160257_support_locking_account',1),('2015_07_08_114333_simplify_tasks',1),('2015_07_19_081332_add_custom_design',1),('2015_07_27_183830_add_pdfmake_support',1),('2015_08_13_084041_add_formats_to_datetime_formats_table',1),('2015_09_04_080604_add_swap_postal_code',1),('2015_09_07_135935_add_account_domain',1),('2015_09_10_185135_add_reminder_emails',1),('2015_10_07_135651_add_social_login',1),('2015_10_21_075058_add_default_tax_rates',1),('2015_10_21_185724_add_invoice_number_pattern',1),('2015_10_27_180214_add_is_system_to_activities',1),('2015_10_29_133747_add_default_quote_terms',1),('2015_11_01_080417_encrypt_tokens',1),('2015_11_03_181318_improve_currency_localization',1),('2015_11_30_133206_add_email_designs',1),('2015_12_27_154513_add_reminder_settings',1),('2015_12_30_042035_add_client_view_css',1),('2016_01_04_175228_create_vendors_table',1),('2016_01_06_153144_add_invoice_font_support',1),('2016_01_17_155725_add_quote_to_invoice_option',1),('2016_01_18_195351_add_bank_accounts',1),('2016_01_24_112646_add_bank_subaccounts',1),('2016_01_27_173015_add_header_footer_option',1),('2016_02_01_135956_add_source_currency_to_expenses',1),('2016_02_25_152948_add_client_password',1),('2016_02_28_081424_add_custom_invoice_fields',1),('2016_03_14_066181_add_user_permissions',1),('2016_03_14_214710_add_support_three_decimal_taxes',1),('2016_03_22_168362_add_documents',1),('2016_03_23_215049_support_multiple_tax_rates',1),('2016_04_16_103943_enterprise_plan',1),('2016_04_18_174135_add_page_size',1),('2016_04_23_182223_payments_changes',1),('2016_05_16_102925_add_swap_currency_symbol_to_currency',1),('2016_05_18_085739_add_invoice_type_support',1),('2016_05_24_164847_wepay_ach',1),('2016_07_08_083802_support_new_pricing',1),('2016_07_13_083821_add_buy_now_buttons',1),('2016_08_10_184027_add_support_for_bots',1),('2016_09_05_150625_create_gateway_types',1),('2016_10_20_191150_add_expense_to_activities',1),('2016_11_03_113316_add_invoice_signature',1),('2016_11_03_161149_add_bluevine_fields',1),('2016_11_28_092904_add_task_projects',1),('2016_12_13_113955_add_pro_plan_discount',1),('2017_01_01_214241_add_inclusive_taxes',1),('2017_02_23_095934_add_custom_product_fields',1),('2017_03_16_085702_add_gateway_fee_location',1),('2017_04_16_101744_add_custom_contact_fields',1),('2017_04_30_174702_add_multiple_database_support',1),('2017_05_10_144928_add_oauth_to_lookups',1),('2017_05_16_101715_add_default_note_to_client',1);
/*!40000 ALTER TABLE `migrations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `email` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `token` varchar(255) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `password_resets`
--

LOCK TABLES `password_resets` WRITE;
/*!40000 ALTER TABLE `password_resets` DISABLE KEYS */;
/*!40000 ALTER TABLE `password_resets` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_libraries`
--

DROP TABLE IF EXISTS `payment_libraries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_libraries` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_libraries`
--

LOCK TABLES `payment_libraries` WRITE;
/*!40000 ALTER TABLE `payment_libraries` DISABLE KEYS */;
INSERT INTO `payment_libraries` VALUES (1,'2017-06-15 04:43:56','2017-06-15 04:43:56','Omnipay',1),(2,'2017-06-15 04:43:56','2017-06-15 04:43:56','PHP-Payments [Deprecated]',1);
/*!40000 ALTER TABLE `payment_libraries` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_methods`
--

DROP TABLE IF EXISTS `payment_methods`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_methods` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `contact_id` int(10) unsigned DEFAULT NULL,
  `account_gateway_token_id` int(10) unsigned DEFAULT NULL,
  `payment_type_id` int(10) unsigned NOT NULL,
  `source_reference` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `routing_number` int(10) unsigned DEFAULT NULL,
  `last4` smallint(5) unsigned DEFAULT NULL,
  `expiration` date DEFAULT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `currency_id` int(10) unsigned DEFAULT NULL,
  `status` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `public_id` int(10) unsigned NOT NULL,
  `bank_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ip` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_methods_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `payment_methods_public_id_index` (`public_id`),
  KEY `payment_methods_user_id_foreign` (`user_id`),
  KEY `payment_methods_contact_id_foreign` (`contact_id`),
  KEY `payment_methods_payment_type_id_foreign` (`payment_type_id`),
  KEY `payment_methods_currency_id_foreign` (`currency_id`),
  KEY `payment_methods_account_gateway_token_id_foreign` (`account_gateway_token_id`),
  CONSTRAINT `payment_methods_account_gateway_token_id_foreign` FOREIGN KEY (`account_gateway_token_id`) REFERENCES `account_gateway_tokens` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_methods_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_methods_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_methods_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `payment_methods_payment_type_id_foreign` FOREIGN KEY (`payment_type_id`) REFERENCES `payment_types` (`id`),
  CONSTRAINT `payment_methods_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_methods`
--

LOCK TABLES `payment_methods` WRITE;
/*!40000 ALTER TABLE `payment_methods` DISABLE KEYS */;
/*!40000 ALTER TABLE `payment_methods` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `payment_statuses`
--

DROP TABLE IF EXISTS `payment_statuses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_statuses` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_statuses`
--

LOCK TABLES `payment_statuses` WRITE;
/*!40000 ALTER TABLE `payment_statuses` DISABLE KEYS */;
INSERT INTO `payment_statuses` VALUES (1,'Pending'),(2,'Voided'),(3,'Failed'),(4,'Completed'),(5,'Partially Refunded'),(6,'Refunded');
/*!40000 ALTER TABLE `payment_statuses` ENABLE KEYS */;
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `public_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_terms_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `payment_terms_public_id_index` (`public_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_terms`
--

LOCK TABLES `payment_terms` WRITE;
/*!40000 ALTER TABLE `payment_terms` DISABLE KEYS */;
INSERT INTO `payment_terms` VALUES (1,7,'Net 7','2017-06-15 04:43:56','2017-06-15 04:43:56',NULL,0,0,1),(2,10,'Net 10','2017-06-15 04:43:56','2017-06-15 04:43:56',NULL,0,0,2),(3,14,'Net 14','2017-06-15 04:43:56','2017-06-15 04:43:56',NULL,0,0,3),(4,15,'Net 15','2017-06-15 04:43:56','2017-06-15 04:43:56',NULL,0,0,4),(5,30,'Net 30','2017-06-15 04:43:56','2017-06-15 04:43:56',NULL,0,0,5),(6,60,'Net 60','2017-06-15 04:43:56','2017-06-15 04:43:56',NULL,0,0,6),(7,90,'Net 90','2017-06-15 04:43:56','2017-06-15 04:43:56',NULL,0,0,7),(8,-1,'Net 0','2017-06-15 04:44:00','2017-06-15 04:44:00',NULL,0,0,0);
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
  `gateway_type_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_types_gateway_type_id_foreign` (`gateway_type_id`),
  CONSTRAINT `payment_types_gateway_type_id_foreign` FOREIGN KEY (`gateway_type_id`) REFERENCES `gateway_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `payment_types`
--

LOCK TABLES `payment_types` WRITE;
/*!40000 ALTER TABLE `payment_types` DISABLE KEYS */;
INSERT INTO `payment_types` VALUES (1,'Apply Credit',NULL),(2,'Bank Transfer',2),(3,'Cash',NULL),(4,'Debit',1),(5,'ACH',2),(6,'Visa Card',1),(7,'MasterCard',1),(8,'American Express',1),(9,'Discover Card',1),(10,'Diners Card',1),(11,'EuroCard',1),(12,'Nova',1),(13,'Credit Card Other',1),(14,'PayPal',3),(15,'Google Wallet',NULL),(16,'Check',NULL),(17,'Carte Blanche',1),(18,'UnionPay',1),(19,'JCB',1),(20,'Laser',1),(21,'Maestro',1),(22,'Solo',1),(23,'Switch',1),(24,'iZettle',1),(25,'Swish',2),(26,'Venmo',NULL),(27,'Money Order',NULL);
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
  `invoice_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned NOT NULL,
  `contact_id` int(10) unsigned DEFAULT NULL,
  `invitation_id` int(10) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `account_gateway_id` int(10) unsigned DEFAULT NULL,
  `payment_type_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `amount` decimal(13,2) NOT NULL,
  `payment_date` date DEFAULT NULL,
  `transaction_reference` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `payer_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `public_id` int(10) unsigned NOT NULL,
  `refunded` decimal(13,2) NOT NULL,
  `payment_status_id` int(10) unsigned NOT NULL DEFAULT '4',
  `routing_number` int(10) unsigned DEFAULT NULL,
  `last4` smallint(5) unsigned DEFAULT NULL,
  `expiration` date DEFAULT NULL,
  `gateway_error` text COLLATE utf8_unicode_ci,
  `email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `payment_method_id` int(10) unsigned DEFAULT NULL,
  `bank_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ip` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `credit_ids` text COLLATE utf8_unicode_ci,
  `private_notes` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payments_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `payments_contact_id_foreign` (`contact_id`),
  KEY `payments_account_gateway_id_foreign` (`account_gateway_id`),
  KEY `payments_user_id_foreign` (`user_id`),
  KEY `payments_payment_type_id_foreign` (`payment_type_id`),
  KEY `payments_invoice_id_index` (`invoice_id`),
  KEY `payments_account_id_index` (`account_id`),
  KEY `payments_client_id_index` (`client_id`),
  KEY `payments_public_id_index` (`public_id`),
  KEY `payments_payment_status_id_foreign` (`payment_status_id`),
  KEY `payments_payment_method_id_foreign` (`payment_method_id`),
  CONSTRAINT `payments_account_gateway_id_foreign` FOREIGN KEY (`account_gateway_id`) REFERENCES `account_gateways` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_payment_method_id_foreign` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payments_payment_status_id_foreign` FOREIGN KEY (`payment_status_id`) REFERENCES `payment_statuses` (`id`),
  CONSTRAINT `payments_payment_type_id_foreign` FOREIGN KEY (`payment_type_id`) REFERENCES `payment_types` (`id`),
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `product_key` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `notes` text COLLATE utf8_unicode_ci NOT NULL,
  `cost` decimal(13,2) NOT NULL,
  `qty` decimal(13,2) DEFAULT NULL,
  `public_id` int(10) unsigned NOT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `custom_value1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `custom_value2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tax_name1` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tax_rate1` decimal(13,3) NOT NULL,
  `tax_name2` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `tax_rate2` decimal(13,3) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `products_user_id_foreign` (`user_id`),
  KEY `products_account_id_index` (`account_id`),
  CONSTRAINT `products_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `products_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
-- Table structure for table `projects`
--

DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `projects` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `public_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `projects_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `projects_user_id_foreign` (`user_id`),
  KEY `projects_account_id_index` (`account_id`),
  KEY `projects_client_id_index` (`client_id`),
  KEY `projects_public_id_index` (`public_id`),
  CONSTRAINT `projects_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `projects_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `projects_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `projects`
--

LOCK TABLES `projects` WRITE;
/*!40000 ALTER TABLE `projects` DISABLE KEYS */;
/*!40000 ALTER TABLE `projects` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `security_codes`
--

DROP TABLE IF EXISTS `security_codes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_codes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned DEFAULT NULL,
  `contact_id` int(10) unsigned DEFAULT NULL,
  `attempts` smallint(6) NOT NULL,
  `code` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `bot_user_id` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `security_codes_bot_user_id_unique` (`bot_user_id`),
  KEY `security_codes_account_id_index` (`account_id`),
  KEY `security_codes_user_id_foreign` (`user_id`),
  KEY `security_codes_contact_id_foreign` (`contact_id`),
  CONSTRAINT `security_codes_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `security_codes_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `security_codes_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `security_codes`
--

LOCK TABLES `security_codes` WRITE;
/*!40000 ALTER TABLE `security_codes` DISABLE KEYS */;
/*!40000 ALTER TABLE `security_codes` ENABLE KEYS */;
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
INSERT INTO `sizes` VALUES (1,'1 - 3'),(2,'4 - 10'),(3,'11 - 50'),(4,'51 - 100'),(5,'101 - 500'),(6,'500+');
/*!40000 ALTER TABLE `sizes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subscriptions`
--

DROP TABLE IF EXISTS `subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `event_id` int(10) unsigned DEFAULT NULL,
  `target_url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `subscriptions_account_id_foreign` (`account_id`),
  CONSTRAINT `subscriptions_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subscriptions`
--

LOCK TABLES `subscriptions` WRITE;
/*!40000 ALTER TABLE `subscriptions` DISABLE KEYS */;
/*!40000 ALTER TABLE `subscriptions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasks`
--

DROP TABLE IF EXISTS `tasks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `client_id` int(10) unsigned DEFAULT NULL,
  `invoice_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `description` text COLLATE utf8_unicode_ci,
  `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
  `public_id` int(10) unsigned NOT NULL,
  `is_running` tinyint(1) NOT NULL DEFAULT '0',
  `time_log` text COLLATE utf8_unicode_ci,
  `project_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tasks_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `tasks_user_id_foreign` (`user_id`),
  KEY `tasks_invoice_id_foreign` (`invoice_id`),
  KEY `tasks_client_id_foreign` (`client_id`),
  KEY `tasks_account_id_index` (`account_id`),
  KEY `tasks_public_id_index` (`public_id`),
  KEY `tasks_project_id_index` (`project_id`),
  CONSTRAINT `tasks_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tasks_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasks`
--

LOCK TABLES `tasks` WRITE;
/*!40000 ALTER TABLE `tasks` DISABLE KEYS */;
/*!40000 ALTER TABLE `tasks` ENABLE KEYS */;
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `rate` decimal(13,3) NOT NULL,
  `public_id` int(10) unsigned NOT NULL,
  `is_inclusive` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `tax_rates_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `tax_rates_user_id_foreign` (`user_id`),
  KEY `tax_rates_account_id_index` (`account_id`),
  CONSTRAINT `tax_rates_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tax_rates_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
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
) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timezones`
--

LOCK TABLES `timezones` WRITE;
/*!40000 ALTER TABLE `timezones` DISABLE KEYS */;
INSERT INTO `timezones` VALUES (1,'Pacific/Midway','(GMT-11:00) Midway Island'),(2,'US/Samoa','(GMT-11:00) Samoa'),(3,'US/Hawaii','(GMT-10:00) Hawaii'),(4,'US/Alaska','(GMT-09:00) Alaska'),(5,'US/Pacific','(GMT-08:00) Pacific Time (US &amp; Canada)'),(6,'America/Tijuana','(GMT-08:00) Tijuana'),(7,'US/Arizona','(GMT-07:00) Arizona'),(8,'US/Mountain','(GMT-07:00) Mountain Time (US &amp; Canada)'),(9,'America/Chihuahua','(GMT-07:00) Chihuahua'),(10,'America/Mazatlan','(GMT-07:00) Mazatlan'),(11,'America/Mexico_City','(GMT-06:00) Mexico City'),(12,'America/Monterrey','(GMT-06:00) Monterrey'),(13,'Canada/Saskatchewan','(GMT-06:00) Saskatchewan'),(14,'US/Central','(GMT-06:00) Central Time (US &amp; Canada)'),(15,'US/Eastern','(GMT-05:00) Eastern Time (US &amp; Canada)'),(16,'US/East-Indiana','(GMT-05:00) Indiana (East)'),(17,'America/Bogota','(GMT-05:00) Bogota'),(18,'America/Lima','(GMT-05:00) Lima'),(19,'America/Caracas','(GMT-04:30) Caracas'),(20,'Canada/Atlantic','(GMT-04:00) Atlantic Time (Canada)'),(21,'America/La_Paz','(GMT-04:00) La Paz'),(22,'America/Santiago','(GMT-04:00) Santiago'),(23,'Canada/Newfoundland','(GMT-03:30) Newfoundland'),(24,'America/Buenos_Aires','(GMT-03:00) Buenos Aires'),(25,'America/Godthab','(GMT-03:00) Greenland'),(26,'Atlantic/Stanley','(GMT-02:00) Stanley'),(27,'Atlantic/Azores','(GMT-01:00) Azores'),(28,'Atlantic/Cape_Verde','(GMT-01:00) Cape Verde Is.'),(29,'Africa/Casablanca','(GMT) Casablanca'),(30,'Europe/Dublin','(GMT) Dublin'),(31,'Europe/Lisbon','(GMT) Lisbon'),(32,'Europe/London','(GMT) London'),(33,'Africa/Monrovia','(GMT) Monrovia'),(34,'Europe/Amsterdam','(GMT+01:00) Amsterdam'),(35,'Europe/Belgrade','(GMT+01:00) Belgrade'),(36,'Europe/Berlin','(GMT+01:00) Berlin'),(37,'Europe/Bratislava','(GMT+01:00) Bratislava'),(38,'Europe/Brussels','(GMT+01:00) Brussels'),(39,'Europe/Budapest','(GMT+01:00) Budapest'),(40,'Europe/Copenhagen','(GMT+01:00) Copenhagen'),(41,'Europe/Ljubljana','(GMT+01:00) Ljubljana'),(42,'Europe/Madrid','(GMT+01:00) Madrid'),(43,'Europe/Paris','(GMT+01:00) Paris'),(44,'Europe/Prague','(GMT+01:00) Prague'),(45,'Europe/Rome','(GMT+01:00) Rome'),(46,'Europe/Sarajevo','(GMT+01:00) Sarajevo'),(47,'Europe/Skopje','(GMT+01:00) Skopje'),(48,'Europe/Stockholm','(GMT+01:00) Stockholm'),(49,'Europe/Vienna','(GMT+01:00) Vienna'),(50,'Europe/Warsaw','(GMT+01:00) Warsaw'),(51,'Europe/Zagreb','(GMT+01:00) Zagreb'),(52,'Europe/Athens','(GMT+02:00) Athens'),(53,'Europe/Bucharest','(GMT+02:00) Bucharest'),(54,'Africa/Cairo','(GMT+02:00) Cairo'),(55,'Africa/Harare','(GMT+02:00) Harare'),(56,'Europe/Helsinki','(GMT+02:00) Helsinki'),(57,'Europe/Istanbul','(GMT+02:00) Istanbul'),(58,'Asia/Jerusalem','(GMT+02:00) Jerusalem'),(59,'Europe/Kiev','(GMT+02:00) Kyiv'),(60,'Europe/Minsk','(GMT+02:00) Minsk'),(61,'Europe/Riga','(GMT+02:00) Riga'),(62,'Europe/Sofia','(GMT+02:00) Sofia'),(63,'Europe/Tallinn','(GMT+02:00) Tallinn'),(64,'Europe/Vilnius','(GMT+02:00) Vilnius'),(65,'Asia/Baghdad','(GMT+03:00) Baghdad'),(66,'Asia/Kuwait','(GMT+03:00) Kuwait'),(67,'Africa/Nairobi','(GMT+03:00) Nairobi'),(68,'Asia/Riyadh','(GMT+03:00) Riyadh'),(69,'Asia/Tehran','(GMT+03:30) Tehran'),(70,'Europe/Moscow','(GMT+04:00) Moscow'),(71,'Asia/Baku','(GMT+04:00) Baku'),(72,'Europe/Volgograd','(GMT+04:00) Volgograd'),(73,'Asia/Muscat','(GMT+04:00) Muscat'),(74,'Asia/Tbilisi','(GMT+04:00) Tbilisi'),(75,'Asia/Yerevan','(GMT+04:00) Yerevan'),(76,'Asia/Kabul','(GMT+04:30) Kabul'),(77,'Asia/Karachi','(GMT+05:00) Karachi'),(78,'Asia/Tashkent','(GMT+05:00) Tashkent'),(79,'Asia/Kolkata','(GMT+05:30) Kolkata'),(80,'Asia/Kathmandu','(GMT+05:45) Kathmandu'),(81,'Asia/Yekaterinburg','(GMT+06:00) Ekaterinburg'),(82,'Asia/Almaty','(GMT+06:00) Almaty'),(83,'Asia/Dhaka','(GMT+06:00) Dhaka'),(84,'Asia/Novosibirsk','(GMT+07:00) Novosibirsk'),(85,'Asia/Bangkok','(GMT+07:00) Bangkok'),(86,'Asia/Ho_Chi_Minh','(GMT+07.00) Ho Chi Minh'),(87,'Asia/Jakarta','(GMT+07:00) Jakarta'),(88,'Asia/Krasnoyarsk','(GMT+08:00) Krasnoyarsk'),(89,'Asia/Chongqing','(GMT+08:00) Chongqing'),(90,'Asia/Hong_Kong','(GMT+08:00) Hong Kong'),(91,'Asia/Kuala_Lumpur','(GMT+08:00) Kuala Lumpur'),(92,'Australia/Perth','(GMT+08:00) Perth'),(93,'Asia/Singapore','(GMT+08:00) Singapore'),(94,'Asia/Taipei','(GMT+08:00) Taipei'),(95,'Asia/Ulaanbaatar','(GMT+08:00) Ulaan Bataar'),(96,'Asia/Urumqi','(GMT+08:00) Urumqi'),(97,'Asia/Irkutsk','(GMT+09:00) Irkutsk'),(98,'Asia/Seoul','(GMT+09:00) Seoul'),(99,'Asia/Tokyo','(GMT+09:00) Tokyo'),(100,'Australia/Adelaide','(GMT+09:30) Adelaide'),(101,'Australia/Darwin','(GMT+09:30) Darwin'),(102,'Asia/Yakutsk','(GMT+10:00) Yakutsk'),(103,'Australia/Brisbane','(GMT+10:00) Brisbane'),(104,'Australia/Canberra','(GMT+10:00) Canberra'),(105,'Pacific/Guam','(GMT+10:00) Guam'),(106,'Australia/Hobart','(GMT+10:00) Hobart'),(107,'Australia/Melbourne','(GMT+10:00) Melbourne'),(108,'Pacific/Port_Moresby','(GMT+10:00) Port Moresby'),(109,'Australia/Sydney','(GMT+10:00) Sydney'),(110,'Asia/Vladivostok','(GMT+11:00) Vladivostok'),(111,'Asia/Magadan','(GMT+12:00) Magadan'),(112,'Pacific/Auckland','(GMT+12:00) Auckland'),(113,'Pacific/Fiji','(GMT+12:00) Fiji');
/*!40000 ALTER TABLE `timezones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `user_accounts`
--

DROP TABLE IF EXISTS `user_accounts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_accounts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id1` int(10) unsigned DEFAULT NULL,
  `user_id2` int(10) unsigned DEFAULT NULL,
  `user_id3` int(10) unsigned DEFAULT NULL,
  `user_id4` int(10) unsigned DEFAULT NULL,
  `user_id5` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_accounts_user_id1_foreign` (`user_id1`),
  KEY `user_accounts_user_id2_foreign` (`user_id2`),
  KEY `user_accounts_user_id3_foreign` (`user_id3`),
  KEY `user_accounts_user_id4_foreign` (`user_id4`),
  KEY `user_accounts_user_id5_foreign` (`user_id5`),
  CONSTRAINT `user_accounts_user_id1_foreign` FOREIGN KEY (`user_id1`) REFERENCES `users` (`id`),
  CONSTRAINT `user_accounts_user_id2_foreign` FOREIGN KEY (`user_id2`) REFERENCES `users` (`id`),
  CONSTRAINT `user_accounts_user_id3_foreign` FOREIGN KEY (`user_id3`) REFERENCES `users` (`id`),
  CONSTRAINT `user_accounts_user_id4_foreign` FOREIGN KEY (`user_id4`) REFERENCES `users` (`id`),
  CONSTRAINT `user_accounts_user_id5_foreign` FOREIGN KEY (`user_id5`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `user_accounts`
--

LOCK TABLES `user_accounts` WRITE;
/*!40000 ALTER TABLE `user_accounts` DISABLE KEYS */;
/*!40000 ALTER TABLE `user_accounts` ENABLE KEYS */;
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
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `first_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `username` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `confirmation_code` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `registered` tinyint(1) NOT NULL DEFAULT '0',
  `confirmed` tinyint(1) NOT NULL DEFAULT '0',
  `notify_sent` tinyint(1) NOT NULL DEFAULT '1',
  `notify_viewed` tinyint(1) NOT NULL DEFAULT '0',
  `notify_paid` tinyint(1) NOT NULL DEFAULT '1',
  `public_id` int(10) unsigned DEFAULT NULL,
  `force_pdfjs` tinyint(1) NOT NULL DEFAULT '0',
  `remember_token` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `news_feed_id` int(10) unsigned DEFAULT NULL,
  `notify_approved` tinyint(1) NOT NULL DEFAULT '1',
  `failed_logins` smallint(6) DEFAULT NULL,
  `dark_mode` tinyint(1) DEFAULT '0',
  `referral_code` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `oauth_user_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `oauth_provider_id` int(10) unsigned DEFAULT NULL,
  `is_admin` tinyint(1) NOT NULL DEFAULT '1',
  `permissions` int(10) unsigned NOT NULL DEFAULT '0',
  `bot_user_id` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_username_unique` (`username`),
  UNIQUE KEY `users_account_id_public_id_unique` (`account_id`,`public_id`),
  UNIQUE KEY `users_oauth_user_id_oauth_provider_id_unique` (`oauth_user_id`,`oauth_provider_id`),
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

--
-- Table structure for table `vendor_contacts`
--

DROP TABLE IF EXISTS `vendor_contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendor_contacts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `vendor_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `first_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `email` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `phone` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `public_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `vendor_contacts_account_id_public_id_unique` (`account_id`,`public_id`),
  KEY `vendor_contacts_user_id_foreign` (`user_id`),
  KEY `vendor_contacts_vendor_id_index` (`vendor_id`),
  CONSTRAINT `vendor_contacts_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_contacts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendor_contacts_vendor_id_foreign` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vendor_contacts`
--

LOCK TABLES `vendor_contacts` WRITE;
/*!40000 ALTER TABLE `vendor_contacts` DISABLE KEYS */;
/*!40000 ALTER TABLE `vendor_contacts` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vendors`
--

DROP TABLE IF EXISTS `vendors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vendors` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `account_id` int(10) unsigned NOT NULL,
  `currency_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `address1` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `address2` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `city` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `state` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `postal_code` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `country_id` int(10) unsigned DEFAULT NULL,
  `work_phone` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `private_notes` text COLLATE utf8_unicode_ci NOT NULL,
  `website` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `is_deleted` tinyint(4) NOT NULL DEFAULT '0',
  `public_id` int(11) NOT NULL DEFAULT '0',
  `vat_number` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `id_number` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `transaction_name` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `vendors_account_id_foreign` (`account_id`),
  KEY `vendors_user_id_foreign` (`user_id`),
  KEY `vendors_country_id_foreign` (`country_id`),
  KEY `vendors_currency_id_foreign` (`currency_id`),
  CONSTRAINT `vendors_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `vendors_country_id_foreign` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `vendors_currency_id_foreign` FOREIGN KEY (`currency_id`) REFERENCES `currencies` (`id`),
  CONSTRAINT `vendors_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vendors`
--

LOCK TABLES `vendors` WRITE;
/*!40000 ALTER TABLE `vendors` DISABLE KEYS */;
/*!40000 ALTER TABLE `vendors` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2017-06-15 10:44:00
