-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 11, 2026 at 06:52 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `lab_ppmkcp_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `asset_id` bigint(20) UNSIGNED NOT NULL,
  `lab_id` bigint(20) UNSIGNED NOT NULL,
  `asset_name` varchar(255) NOT NULL,
  `asset_status` varchar(50) NOT NULL,
  `asset_count` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`asset_id`, `lab_id`, `asset_name`, `asset_status`, `asset_count`, `created_at`, `updated_at`) VALUES
(1, 1, 'Milimeter', 'Available', 3, '2026-01-11 15:52:00', '2026-01-11 15:52:06'),
(2, 2, 'Pinggan', 'Limited', 5, '2026-01-11 16:11:14', '2026-01-11 16:11:14');

-- --------------------------------------------------------

--
-- Table structure for table `clusters`
--

CREATE TABLE `clusters` (
  `cluster_id` bigint(20) UNSIGNED NOT NULL,
  `cluster_name` varchar(255) NOT NULL,
  `cluster_description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clusters`
--

INSERT INTO `clusters` (`cluster_id`, `cluster_name`, `cluster_description`, `created_at`, `updated_at`) VALUES
(1, 'Kluster Sains Gunaan & Teknologi', 'Makmal biologi, fizik, kimia makanan, matematik dan statistik.', '2026-01-11 13:56:49', '2026-01-11 13:56:49'),
(2, 'Kluster Teknologi Kejuruteraan Awam & Kimia', 'Merangkumi bengkel awam, bioproses, air & alam sekitar, geoteknik, dan instrumen kimia.', '2026-01-11 13:56:49', '2026-01-11 13:56:49'),
(3, 'Kluster Teknologi Kejuruteraan Elektrik & Multimedia', 'Fokus kepada asas elektrik, elektronik kuasa, multimedia, rangkaian komputer dan komunikasi.', '2026-01-11 13:56:49', '2026-01-11 13:56:49'),
(4, 'Kluster Teknologi Kejuruteraan Mekanikal & Pengangkutan', 'Menawarkan kemudahan automotif, fabrikasi, termodinamik, tekstil, dan ujian bahan.', '2026-01-11 13:56:49', '2026-01-11 13:56:49');

-- --------------------------------------------------------

--
-- Table structure for table `labs`
--

CREATE TABLE `labs` (
  `lab_id` bigint(20) UNSIGNED NOT NULL,
  `cluster_id` bigint(20) UNSIGNED NOT NULL,
  `supervisor_id` bigint(20) UNSIGNED DEFAULT NULL,
  `lab_name` varchar(255) NOT NULL,
  `lab_description` text DEFAULT NULL,
  `lab_capacity` int(11) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lab_supervisor_labs`
--

CREATE TABLE `lab_supervisor_labs` (
  `lab_supervisor_lab_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `lab_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `labs`
--

INSERT INTO `labs` (`lab_id`, `cluster_id`, `supervisor_id`, `lab_name`, `lab_description`, `lab_capacity`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Bengkel Teknologi Masonri (COR SUNR)', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:49', '2026-01-11 13:56:49'),
(2, 1, 2, 'Makmal Analisis Makanan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:49', '2026-01-11 13:56:49'),
(3, 1, 3, 'Makmal Biokimia Makanan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:49', '2026-01-11 13:56:49'),
(4, 1, 4, 'Makmal Biologi Struktur Dan Fungsi 1', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:49', '2026-01-11 13:56:49'),
(5, 1, 1, 'Makmal Biologi Struktur Dan Fungsi 2', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(6, 1, 5, 'Makmal Biologi Struktur Dan Fungsi 3', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(7, 1, 6, 'Makmal Fizik Bahan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(8, 1, 7, 'Makmal Fizik Elektrik Dan Magnet (Getaran Dan Gelombang)', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(9, 1, 8, 'Makmal Fizik Elektronik', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(10, 1, 8, 'Makmal Fizik Instrumentasi', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(11, 1, 9, 'Makmal Fizik Kesihatan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(12, 1, 10, 'Makmal Fizik Laser', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(13, 1, 11, 'Makmal Fizik Optik', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(14, 1, 9, 'Makmal Fizik Sinaran', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(15, 1, 7, 'Makmal Fizik Statik Dan Mekanik', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(16, 1, 12, 'Makmal Fizik Teknologi Nano', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(17, 1, 13, 'Makmal Gunasama Biologi', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(18, 1, 14, 'Makmal Gunasama Fizik 1', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(19, 1, 14, 'Makmal Gunasama Fizik 2', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(20, 1, 15, 'Makmal Gunasama Kimia 1', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(21, 1, 15, 'Makmal Gunasama Kimia 2', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(22, 1, 16, 'Makmal Gunasama Kimia 3', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(23, 1, 17, 'Makmal Gunasama Kimia 4', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(24, 1, 18, 'Makmal Gunasama Matematik 1', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(25, 1, 19, 'Makmal Gunasama Matematik 2', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(26, 1, 10, 'Makmal Gunasama Statistik', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(27, 1, 20, 'Makmal Instrumentasi Makanan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(28, 1, 2, 'Makmal Kejuruteraan Makanan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(29, 1, 21, 'Makmal Matematik 1', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(30, 1, 21, 'Makmal Matematik 2', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(31, 1, 22, 'Makmal Mikrobiologi Makanan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(32, 1, 18, 'Makmal Pemakanan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(33, 1, 23, 'Makmal Pembangunan Produk Makanan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(34, 1, 23, 'Makmal Pemprosesan Makanan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(35, 1, 4, 'Makmal Sains Gunaan 1', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(36, 1, 1, 'Makmal Sains Gunaan 2', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(37, 1, 19, 'Makmal Statistik 1', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(38, 1, 19, 'Makmal Statistik 2', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(39, 1, 5, 'Makmal Teknologi Bakeri, Snek Dan Konfeksionari', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(40, 1, 22, 'Makmal Teknologi Sains Dan Kejuruteraan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(41, 1, 3, 'Makmal Ujirasa Dan Sensori Makanan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(42, 2, 24, 'Bengkel Teknologi Fabrikasi Besi', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(43, 2, 25, 'Bengkel Teknologi Kejuruteraan Komposit', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(44, 2, 26, 'Bengkel Teknologi Kejuruteraan Perkayuan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(45, 2, 27, 'Bengkel Teknologi Kejuruteraan Perpaipan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(46, 2, 25, 'Bengkel Teknologi Kejuruteraan Struktur Berat', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(47, 2, 24, 'Bengkel Teknologi Konkrit', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(48, 2, 26, 'Bengkel Teknologi Perabot', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(49, 2, 28, 'Bilik Bahan Kimia', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(50, 2, 29, 'Bilik Sisa Kimia', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(51, 2, 30, 'Makmal Analitikal', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(52, 2, 31, 'Makmal Aplikasi Komputer Kejuruteraan Awam', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(53, 2, 32, 'Makmal Bioproses Hiliran 1', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(54, 2, 33, 'Makmal Bioproses Hiliran 2', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(55, 2, 34, 'Makmal Bioproses Huluan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(56, 2, 35, 'Makmal Kejuruteraan Tindakbalas Kimia', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(57, 2, 30, 'Makmal Komputer Teknologi Kejuruteraan Kimia', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(58, 2, 36, 'Makmal Lukisan Cad Kejuruteraan Awam 1', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(59, 2, 36, 'Makmal Lukisan Cad Kejuruteraan Awam 2', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(60, 2, 37, 'Makmal Lukisan Teknologi Kejuruteraan Awam 1', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(61, 2, 38, 'Makmal Lukisan Teknologi Kejuruteraan Awam 2', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(62, 2, 32, 'Makmal Mekanik Bendalir', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(63, 2, 35, 'Makmal Pemindahan Haba & Jisim', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(64, 2, 39, 'Makmal Proses Instrumentasi', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(65, 2, 39, 'Makmal Proses Pemisahan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(66, 2, 40, 'Makmal Teknologi Bahan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(67, 2, 28, 'Makmal Teknologi Kejuruteraan Air Dan Air Sisa', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(68, 2, 41, 'Makmal Teknologi Kejuruteraan Air Pollution', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(69, 2, 42, 'Makmal Teknologi Kejuruteraan Alam Sekitar', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(70, 2, 43, 'Makmal Teknologi Kejuruteraan Geomatik', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(71, 2, 44, 'Makmal Teknologi Kejuruteraan Geoteknik', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(72, 2, 45, 'Makmal Teknologi Kejuruteraan Jalan Raya', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(73, 2, 38, 'Makmal Teknologi Kejuruteraan Mekanik Bendalir', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(74, 2, 27, 'Makmal Teknologi Kejuruteraan Perkhidmatan Bangunan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(75, 2, 31, 'Makmal Teknologi Kejuruteraan Struktur Ringan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(76, 2, 37, 'Makmal Teknologi Kejuruteraan Sumber Air', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(77, 2, 41, 'Makmal Teknologi Kejuruteraan Trafik', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(78, 2, 33, 'Makmal Termodinamik (KTKAK)', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(79, 3, 46, 'Makmal Asas Elektrik Dan Elektronik 1', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(80, 3, 47, 'Makmal Asas Elektrik Dan Elektronik 2', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(81, 3, 48, 'Makmal Bahasa Antarabangsa', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(82, 3, 49, 'Makmal Baikpulih Komputer', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(83, 3, 50, 'Makmal Elektronik Kuasa', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(84, 3, 51, 'Makmal Grafik & Animasi', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(85, 3, 49, 'Makmal Gunasama Komputer', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(86, 3, 52, 'Makmal Komputer Sava', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(87, 3, 53, 'Makmal Mikropengawal', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(88, 3, 49, 'Makmal Pengaturcaraan Internet', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(89, 3, 54, 'Makmal Pepasangan Elektrik', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(90, 3, 55, 'Makmal Peralatan Dan Pengujian', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(91, 3, 56, 'Makmal Projek', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(92, 3, 52, 'Makmal Projek Diploma 1', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(93, 3, 51, 'Makmal Projek Diploma 2', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(94, 3, 46, 'Makmal Rekabentuk Berbantu Komputer', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(95, 3, 57, 'Makmal Teknologi Automasi Industri (IIOT-STM)', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(96, 3, 58, 'Makmal Teknologi Elektronik & Digit 2', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(97, 3, 59, 'Makmal Teknologi Elektronik Dan Digit 1', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(98, 3, 60, 'Makmal Teknologi Jalur Lebar Tanpa Wayar', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(99, 3, 57, 'Makmal Teknologi Kawalan Industri', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(100, 3, 50, 'Makmal Teknologi Kuasa Elektrik', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(101, 3, 53, 'Makmal Teknologi Mesin Dan Pemacu', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(102, 3, 54, 'Makmal Teknologi Mikrokomputer', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(103, 3, 58, 'Makmal Teknologi Multimedia Dan Penyiaran (Cybersecurity)', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(104, 3, 61, 'Makmal Teknologi Papan Litar Tercetak', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(105, 3, 61, 'Makmal Teknologi Pemasangan Dan Pembuatan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(106, 3, 59, 'Makmal Teknologi Pengukuran & Peralatan 1', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(107, 3, 62, 'Makmal Teknologi Pengukuran Dan Peralatan 2 (Makmal ICOE-Rel)', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(108, 3, 54, 'Makmal Teknologi Pepasangan Industri', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(109, 3, 60, 'Makmal Teknologi Peranti Industri', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(110, 3, 56, 'Makmal Teknologi Rangkaian Komputer', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(111, 3, 47, 'Makmal Teknologi Sistem Komunikasi', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(112, 3, 55, 'Makmal Tenaga Boleh Diperbaharui', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(113, 3, 48, 'Makmal Umum Bahasa Inggeris 1', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(114, 3, 48, 'Makmal Umum Bahasa Inggeris 2', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(115, 4, 63, 'Bengkel Dinamik Kenderaan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(116, 4, 64, 'Bengkel Kejuruteraan Teknologi Loji', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(117, 4, 65, 'Bengkel Teknologi Automotif', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(118, 4, 66, 'Bengkel Teknologi Celupan Dan Kemasan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(119, 4, 67, 'Bengkel Teknologi Elektrik Automotif', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(120, 4, 68, 'Bengkel Teknologi Kejuruteraan Mekanikal Teaching Factory', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(121, 4, 69, 'Bengkel Teknologi Kimpalan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(122, 4, 70, 'Bengkel Teknologi Pembuatan Moden', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(123, 4, 71, 'Bengkel Teknologi Pemesinan Asas', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(124, 4, 72, 'Bengkel Teknologi Tuangan Logam', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(125, 4, 73, 'Makmal CFD (Computer Fluid Dynamics)', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(126, 4, 74, 'Makmal Getaran Dan Kebisingan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:50', '2026-01-11 13:56:50'),
(127, 4, 67, 'Makmal Instrumentasi Dan Kawalan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(128, 4, 74, 'Makmal Instrumentasi Loji', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(129, 4, 75, 'Makmal Lukisan Kejuruteraan Mekanikal 1', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(130, 4, 72, 'Makmal Lukisan Kejuruteraan Mekanikal 2', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(131, 4, 76, 'Makmal Mekanik Bendalir', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(132, 4, 77, 'Makmal Mekanik Mesin', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(133, 4, 77, 'Makmal Mekanik Pepejal', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(134, 4, 78, 'Makmal Pengujian Keselesaan Terma', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(135, 4, 79, 'Makmal Pengujian Tekstil', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(136, 4, 70, 'Makmal Projek Loji', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(137, 4, 80, 'Makmal Projek Teknologi Pembungkusan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(138, 4, 81, 'Makmal Sains Bahan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(139, 4, 82, 'Makmal Sistem Pengujian', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(140, 4, 75, 'Makmal Statik Dan Dinamik', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(141, 4, 83, 'Makmal Teknologi Apparel', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(142, 4, 73, 'Makmal Teknologi Automasi Industri Dan Robotik', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(143, 4, 82, 'Makmal Teknologi Bahan Pembungkusan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(144, 4, 66, 'Makmal Teknologi Bukan Tenun', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(145, 4, 84, 'Makmal Teknologi CAD/CAM', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(146, 4, 78, 'Makmal Teknologi Fabrikasi', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(147, 4, 71, 'Makmal Teknologi Industri Dan Ergonomik', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(148, 4, 63, 'Makmal Teknologi Kait', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(149, 4, 85, 'Makmal Teknologi Komponen Pembungkusan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(150, 4, 65, 'Makmal Teknologi Mekanikal Loji', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(151, 4, 80, 'Makmal Teknologi Mesin Pembungkusan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(152, 4, 64, 'Makmal Teknologi Pemesinan Berbantu Komputer', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(153, 4, 81, 'Makmal Teknologi Pengukuran (Metrologi)', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(154, 4, 79, 'Makmal Teknologi Pintalan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(155, 4, 85, 'Makmal Teknologi Rekabentuk Dan Simulasi Pembungkusan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(156, 4, 76, 'Makmal Teknologi Sistem Automotif', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(157, 4, 83, 'Makmal Teknologi Tenunan', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51'),
(158, 4, 84, 'Makmal Termodinamik (KTKMP)', 'Maklumat terperinci belum disediakan. Sila hubungi penyelaras makmal.', 30, '2026-01-11 13:56:51', '2026-01-11 13:56:51');

-- --------------------------------------------------------

--
-- Table structure for table `lab_bookings`
--

CREATE TABLE `lab_bookings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `lab_id` bigint(20) UNSIGNED NOT NULL,
  `booking_date` date NOT NULL,
  `time_slot` varchar(20) NOT NULL,
  `status` enum('Approved','Cancelled','Rejected') NOT NULL DEFAULT 'Approved',
  `rejection_reason` text DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_bookings`
--

INSERT INTO `lab_bookings` (`id`, `user_id`, `lab_id`, `booking_date`, `time_slot`, `status`, `rejection_reason`, `cancellation_reason`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '2026-01-15', '12:00-13:00', 'Cancelled', NULL, 'Tukar tarikh', '2026-01-11 14:12:20', '2026-01-11 17:18:28'),
(2, 4, 1, '2026-01-21', '11:00-12:00', 'Rejected', 'Under Maintenance', NULL, '2026-01-11 15:50:37', '2026-01-11 15:52:34'),
(3, 2, 1, '2026-01-20', '12:00-13:00', 'Approved', NULL, NULL, '2026-01-11 16:08:28', '2026-01-11 16:08:28');

-- --------------------------------------------------------

--
-- Table structure for table `lab_reservations`
--

CREATE TABLE `lab_reservations` (
  `reservation_id` bigint(20) UNSIGNED NOT NULL,
  `booking_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `activity_details` text NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `ic_no` varchar(20) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(30) NOT NULL,
  `affiliation_type` enum('uthm','public') NOT NULL,
  `cluster_id` bigint(20) UNSIGNED DEFAULT NULL,
  `public_agency_type` enum('private','government') DEFAULT NULL,
  `public_sector` varchar(255) DEFAULT NULL,
  `government_info` varchar(255) DEFAULT NULL,
  `include_equipment` tinyint(1) NOT NULL DEFAULT 0,
  `include_chemicals` tinyint(1) NOT NULL DEFAULT 0,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_student` tinyint(1) NOT NULL DEFAULT 0,
  `supervisor_name` varchar(255) DEFAULT NULL,
  `supervisor_matric` varchar(100) DEFAULT NULL,
  `supervisor_phone` varchar(30) DEFAULT NULL,
  `supervisor_email` varchar(255) DEFAULT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `lab_reservations`
--

INSERT INTO `lab_reservations` (`reservation_id`, `booking_id`, `title`, `activity_details`, `full_name`, `ic_no`, `email`, `phone`, `affiliation_type`, `cluster_id`, `public_agency_type`, `public_sector`, `government_info`, `include_equipment`, `include_chemicals`, `booking_date`, `start_time`, `end_time`, `is_student`, `supervisor_name`, `supervisor_matric`, `supervisor_phone`, `supervisor_email`, `document_path`, `created_at`, `updated_at`) VALUES
(1, 1, 'BDP II - ENHANCING THE FOAMING PROPERTIES OF PLANT-BASED MILK THROUGH ENZYMATIC HYDROLYSIS', 'Finding out the optimum enzyme ratio for best milk foamability', 'CI230027', '020102050466', 'ci230027@student.uthm.edu.my', '0196475077', 'uthm', 1, NULL, NULL, NULL, 0, 0, '2026-01-15', '12:00:00', '00:00:13', 1, 'Ruslan Talha', '01234', '0199999993', 'ruslan@uthm.edu.my', 'uploads/reservations/reservation_1_1768140740.pdf', '2026-01-11 14:12:20', '2026-01-11 14:12:20'),
(2, 2, 'Test', 'Test Desc', 'Mashitah', '020102050468', 'mashi@example.com', '0196475066', 'uthm', 1, NULL, NULL, NULL, 0, 0, '2026-01-21', '11:00:00', '00:00:12', 0, NULL, NULL, NULL, NULL, NULL, '2026-01-11 15:50:37', '2026-01-11 15:50:37'),
(3, 3, 'Test', 'Test Desc', 'CI230027', '020102050466', 'ci230027@student.uthm.edu.my', '0196475077', 'uthm', 1, NULL, NULL, NULL, 0, 0, '2026-01-20', '12:00:00', '00:00:13', 1, 'Ruslan Talha', '01234', '0199999993', 'ruslan@uthm.edu.my', NULL, '2026-01-11 16:08:28', '2026-01-11 16:08:28');

-- --------------------------------------------------------

--
-- Table structure for table `reservation_chemicals`
--

CREATE TABLE `reservation_chemicals` (
  `chemical_id` bigint(20) UNSIGNED NOT NULL,
  `reservation_id` bigint(20) UNSIGNED NOT NULL,
  `chemical_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `ppe_required` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reservation_equipment`
--

CREATE TABLE `reservation_equipment` (
  `equipment_id` bigint(20) UNSIGNED NOT NULL,
  `reservation_id` bigint(20) UNSIGNED NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supervisors`
--

CREATE TABLE `supervisors` (
  `supervisor_id` bigint(20) UNSIGNED NOT NULL,
  `cluster_id` bigint(20) UNSIGNED NOT NULL,
  `supervisor_name` varchar(255) NOT NULL,
  `supervisor_email` varchar(255) DEFAULT NULL,
  `supervisor_room_no` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisors`
--

INSERT INTO `supervisors` (`supervisor_id`, `cluster_id`, `supervisor_name`, `supervisor_email`, `supervisor_room_no`, `created_at`, `updated_at`) VALUES
(1, 1, 'Muhammad Izzat Hafizuddin bin Mohd Shah', 'izzath@uthm.edu.my', '2.J.3.011', '2026-01-11 13:56:49', '2026-01-11 14:09:33'),
(2, 1, 'Mohd Yusof bin Mohd Nor', 'yusofn@uthm.edu.my', '2.J.1.074', '2026-01-11 13:56:49', '2026-01-11 14:09:33'),
(3, 1, 'Siti Zarah binti Imam Tohit', 'sitizarah@uthm.edu.my', '2.J.1.036', '2026-01-11 13:56:49', '2026-01-11 14:09:33'),
(4, 1, 'Nur Atiera binti Ramli', 'nuratiera@uthm.edu.my', '2.J.3.017', '2026-01-11 13:56:49', '2026-01-11 14:09:33'),
(5, 1, 'Aliff Hamzah bin Kamaruddin', 'aliffh@uthm.edu.my', '2.J.1.061', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(6, 1, 'Kamarul Affendi bin Hamdan', 'affendi@uthm.edu.my', '2.J.2.001', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(7, 1, 'Norsidah binti Harun', 'norsidah@uthm.edu.my', '2.J.2.016', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(8, 1, 'Muhammad Ghazalli bin Ibrahim', 'ghazalli@uthm.edu.my', '2.J.2.055', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(9, 1, 'Sufian bin Abd Rahim', 'sufian@uthm.edu.my', '2.J.1.007', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(10, 1, 'Abu Laith bin Solihan', 'abulaith@uthm.edu.my', '2.A1.1.074', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(11, 1, 'Siti Maisarah binti Rahim', 'maisarah@uthm.edu.my', '2.J.2.010', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(12, 1, 'Mohammad Khairul Nahar bin Kassim', 'knahar@uthm.edu.my', '2.J.2.046', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(13, 1, 'Fatin Nur Ain binti Kemat', 'fatinnur@uthm.edu.my', '2.J.3.047', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(14, 1, 'Fathin Jasleen binti Abd Basit', 'fathin@uthm.edu.my', '2.J.3.049', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(15, 1, 'Mohd Azman bin Mohd Sadikin', 'mdazman@uthm.edu.my', '2.J.3.025', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(16, 1, 'Norhafizam binti Mohamed Yusof', 'hafizam@uthm.edu.my', '2.J.3.041', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(17, 1, 'Zharif Aiman bin Abdul Mutalib', 'zharif@uthm.edu.my', '2.J.3.044', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(18, 1, 'Norzieyana binti Md. Arshad', 'norzieyana@uthm.edu.my', '2.J.1.033', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(19, 1, 'Mohamad Sidiq bin Mohd Basir', 'sidiq@uthm.edu.my', '2.J.3.007', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(20, 1, 'Mohd Marhafidz bin Marjori', 'mhafidz@uthm.edu.my', '2.J.1.016', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(21, 1, 'Mohamad Khidir bin Mohd Ibrahim', 'khidir@uthm.edu.my', '2.J.3.037', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(22, 1, 'Nurul Fatihah binti Mohd Jailan', 'fatihah@uthm.edu.my', '2.J.2.005', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(23, 1, 'Muhammad Hafizul Iqbal bin Mastor', 'mhafizul@uthm.edu.my', '2.J.1.067', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(24, 2, 'Hanafiah bin Ismail', 'hanafiah@uthm.edu.my', '2.F.1.001', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(25, 2, 'Tc. Hazri bin Mokhtar', 'hazri@uthm.edu.my', '2.F.1.036', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(26, 2, 'Tc. Mudzaffar Syah bin Kamarudin', 'mudzaffar@uthm.edu.my', '2.G.1.009', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(27, 2, 'Nadia binti Kasim', 'nadiakasim@uthm.edu.my', '2.G.1.018', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(28, 2, 'Mohamad Zayani Zakwan bin Mohd Zin', 'zayani@uthm.edu.my', '2.F.1.020', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(29, 2, 'Muhammad Fadhli Hakim bin Ahmad', 'mdfadhli@uthm.edu.my', '2.F.1.025', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(30, 2, 'Mohamad Zulhilmi bin Paiman', 'zulhilmip@uthm.edu.my', '2.H.2.016', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(31, 2, 'Tc. Nurhayani binti Ujurmudi', 'nurhayani@uthm.edu.my', '2.G.2.003', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(32, 2, 'Ts. Noor Aminadia binti Baharuddin', 'aminadia@uthm.edu.my', '2.H.1.010', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(33, 2, 'Nur Farrah Ain binti SM Bakri', 'nurfarrah@uthm.edu.my', '2.H.2.001', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(34, 2, 'Aziah binti Abu Samah', 'aziah@uthm.edu.my', '2.H.1.022', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(35, 2, 'Nurhasikin binti Tugiman', 'nurhasikin@uthm.edu.my', '2.H.1.054', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(36, 2, 'Nur Ain binti Mohamad', 'nurain@uthm.edu.my', '2.G.3.004', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(37, 2, 'Harina binti Md Amin', 'harina@uthm.edu.my', '2.F.1.042', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(38, 2, 'Tc. Mohd Faizal Riza bin Kamian', 'faizalr@uthm.edu.my', '2.F.1.047', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(39, 2, 'Muhammad Faizi Bin Ibrahim', 'mfaizi@uthm.edu.my', '2.H.1.042', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(40, 2, 'Mohd Redzuan bin Mohd Nor', 'mredzuan@uthm.edu.my', '2.H.1.016', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(41, 2, 'Siti Nadia Syuhada binti Mohd Satti', 'sitinadia@uthm.edu.my', '2.G.2.021', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(42, 2, 'Nik Shamimi Nazma binti Nik Mohamed Kamal', 'nikshamimi@uthm.edu.my', '2.G.1.070', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(43, 2, 'Siti Khadijah binti Md Nor', 'khadijahn@uthm.edu.my', '2.G.1.036', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(44, 2, 'Faeqatul Nabila binti Zubir', 'faeqatul@uthm.edu.my', '2.H.1.037', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(45, 2, 'Tc. Muhamad Khairul Fitri bin Sarimin', 'khairulfs@uthm.edu.my', '2.G.1.042', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(46, 3, 'Muhamad Asyraf bin Mohammad Hamin', 'asyraf@uthm.edu.my', '2.B.3.013', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(47, 3, 'Tc. Nor Azizah binti Arif', 'naziza@uthm.edu.my', '2.B.3.017', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(48, 3, 'Maizatulfiza binti Yahya', 'fizayahya@uthm.edu.my', '2.A1.1.025', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(49, 3, 'Muhamad Hisamuddin bin Pasori', 'hisamuddin@uthm.edu.my', '2.J.3.038', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(50, 3, 'Saidatul Nazriyah binti Rosli', 'saidatul@uthm.edu.my', '2.B.1.018', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(51, 3, 'Tc. Muhd Amin bin Saad', 'muhdamin@uthm.edu.my', '2.H.3.003', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(52, 3, 'Mohd Niza bin Samsudin', 'niza@uthm.edu.my', '2.H.3.001', '2026-01-11 13:56:50', '2026-01-11 14:09:33'),
(53, 3, 'Mohd Shahnas bin Jamaludin', 'shahnas@uthm.edu.my', '2.B.1.005', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(54, 3, 'Izam Iskandar bin Abdullah', 'izam@uthm.edu.my', '2.B.1.010', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(55, 3, 'Mohamad Syah Rizal bin Abdullah', 'syahrizal@uthm.edu.my', '2.B.1.024', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(56, 3, 'Issam Suhari bin Iskandar', 'issam@uthm.edu.my', '2.B.3.007', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(57, 3, 'Fadzil bin Esa', 'fadzil@uthm.edu.my', '2.B.2.020', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(58, 3, 'Muhammad Helmi bin Khamis', 'helmi@uthm.edu.my', '2.B.4.010', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(59, 3, 'Muhammad Zulhilmi bin Md Nor', 'mzulhilmi@uthm.edu.my', '2.B.2.040', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(60, 3, 'Ahyat bin Mohamed Zaini', 'ahyat@uthm.edu.my', '2.B.2.001', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(61, 3, 'Abdul Hariz bin Ahmad', 'abdulhariz@uthm.edu.my', '2.B.2.005', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(62, 3, 'Amarudeen bin Amir', 'amarudeen@uthm.edu.my', '2.B.3.039', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(63, 4, 'Muhammad Izzat bin Che Mangsor', 'izzatm@uthm.edu.my', '2.D.3.005', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(64, 4, 'Mohd Zul Haffizi bin Mohd Sihat', 'zulhaffizi@uthm.edu.my', '2.E.1.020', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(65, 4, 'Mohamed Ihsan Sabri bin Mohamed Nazar', 'ihsann@uthm.edu.my', '2.C.1.045', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(66, 4, 'Ahmad Yazid bin Buang', 'yazid@uthm.edu.my', '2.E.1.039', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(67, 4, 'Mohammad Khidhir bin Mohd Sharif', 'khidhir@uthm.edu.my', '2.D.3.001', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(68, 4, 'Mahathir bin Mun Talib', 'mahathirm@uthm.edu.my', '2.K.1.006', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(69, 4, 'Muhd Syafiq bin Ayub', 'syafiqayub@uthm.edu.my', '2.C.1.023', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(70, 4, 'Muhammad Zaidi bin Jaafar', 'mzaidi@uthm.edu.my', '2.C.1.019', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(71, 4, 'Muhammad Khalis bin Daut', 'khalis@uthm.edu.my', '2.C.4.013', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(72, 4, 'Faiq Khairi bin Suhaimi', 'faiqkhairi@uthm.edu.my', '2.C.2.015', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(73, 4, 'Mohd Akmal Hakim bin Razak', 'akmalhakim@uthm.edu.my', '2.D.4.005', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(74, 4, 'Muhammad Amiruddin bin Hassan Al Ashari', 'amiruddinh@uthm.edu.my', '2.D.3.017', '2026-01-11 13:56:50', '2026-01-11 14:09:34'),
(75, 4, 'Salihudin bin Abd.Razak', 'salih@uthm.edu.my', '2.C.2.019', '2026-01-11 13:56:51', '2026-01-11 14:09:34'),
(76, 4, 'Muhammad Hanif bin Ismail', 'hanifbi@uthm.edu.my', '2.D.1.007', '2026-01-11 13:56:51', '2026-01-11 14:09:34'),
(77, 4, 'Mohd Nazrin bin Ya\'akof', 'mnazrin@uthm.edu.my', '2.C.3.016', '2026-01-11 13:56:51', '2026-01-11 14:09:34'),
(78, 4, 'Ahmad Syakir bin Mohamad Jamil', 'syakir@uthm.edu.my', '2.E.1.014', '2026-01-11 13:56:51', '2026-01-11 14:09:34'),
(79, 4, 'Tc. Mohd Sahrill bin Wagiman', 'msahrill@uthm.edu.my', '2.E.1.033', '2026-01-11 13:56:51', '2026-01-11 14:09:34'),
(80, 4, 'Mohamad Firdaus bin Saat', 'mfirdauss@uthm.edu.my', '2.D.1.025', '2026-01-11 13:56:51', '2026-01-11 14:09:34'),
(81, 4, 'Muhamad Riduan bin Basri', 'mriduan@uthm.edu.my', '2.C.1.001', '2026-01-11 13:56:51', '2026-01-11 14:09:34'),
(82, 4, 'Norsahidah binti Abdullah', 'norsahidah@uthm.edu.my', '2.D.1.019', '2026-01-11 13:56:51', '2026-01-11 14:09:34'),
(83, 4, 'Mohamad Shamirul Asyraf bin Mohamad Azmy', 'shamirul@uthm.edu.my', '2.D.1.001', '2026-01-11 13:56:51', '2026-01-11 14:09:34'),
(84, 4, 'Kamarul Qawiem bin Md. Som', 'qawiem@uthm.edu.my', '2.C.3.012', '2026-01-11 13:56:51', '2026-01-11 14:09:34'),
(85, 4, 'Mohamad Amirul Syafuan bin Sukimin', 'amiruls@uthm.edu.my', '2.D.2.005', '2026-01-11 13:56:51', '2026-01-11 14:09:34');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `ic_no` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `user_type` enum('public','uthm_staff','uthm_student','super_admin','cluster_admin','lab_supervisor','admin') NOT NULL DEFAULT 'public',
  `cluster_id` bigint(20) UNSIGNED DEFAULT NULL,
  `staff_status` enum('Yes','No') NOT NULL DEFAULT 'No',
  `department` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `student_staff_id` varchar(50) DEFAULT NULL,
  `notify_email` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `phone`, `ic_no`, `password`, `user_type`, `cluster_id`, `staff_status`, `department`, `created_at`, `updated_at`, `student_staff_id`) VALUES
(1, 'Admin', 'admin@uthm.edu.my', NULL, NULL, '$2y$10$ALmp37SMowg73tdB3we1YuW0NyXJbgnlk3gtHcnhRWqWec8QjeAHS', 'super_admin', NULL, 'No', NULL, '2026-01-11 13:55:35', '2026-01-11 13:58:30', NULL),
(2, 'Pengurus Kluster Teknologi Kejuruteraan Elektrik & Multimedia', 'ktkem@uthm.edu.my', NULL, NULL, '$2y$10$ALmp37SMowg73tdB3we1YuW0NyXJbgnlk3gtHcnhRWqWec8QjeAHS', 'cluster_admin', 3, 'No', NULL, '2026-01-11 16:12:00', '2026-01-11 16:12:00', NULL),
(3, 'Pengurus Kluster Teknologi Kejuruteraan Awam & Kimia', 'ktkak@uthm.edu.my', NULL, NULL, '$2y$10$ALmp37SMowg73tdB3we1YuW0NyXJbgnlk3gtHcnhRWqWec8QjeAHS', 'cluster_admin', 2, 'No', NULL, '2026-01-11 16:12:00', '2026-01-11 16:12:00', NULL),
(4, 'Pengurus Kluster Teknologi Kejuruteraan Mekanikal & Pengangkutan', 'ktkmp@uthm.edu.my', NULL, NULL, '$2y$10$ALmp37SMowg73tdB3we1YuW0NyXJbgnlk3gtHcnhRWqWec8QjeAHS', 'cluster_admin', 4, 'No', NULL, '2026-01-11 16:12:00', '2026-01-11 16:12:00', NULL),
(5, 'Pengurus Kluster Sains Gunaan & Teknologi', 'ksgt@uthm.edu.my', NULL, NULL, '$2y$10$ALmp37SMowg73tdB3we1YuW0NyXJbgnlk3gtHcnhRWqWec8QjeAHS', 'cluster_admin', 1, 'No', NULL, '2026-01-11 16:12:00', '2026-01-11 16:12:00', NULL);
(6, 'CI230027', 'ci230027@student.uthm.edu.my', NULL, NULL, '$2y$10$c//T1e4VkPzy2uBfrrLu.O9jqiLm5SaRh95gIlsJ8AQIrEKrUvOp6', 'uthm_student', 1, 'No', NULL, '2026-01-11 13:58:30', '2026-01-11 13:58:30', 'ci230027'),
(7, 'Tengku Mazlina', 'mazlina@gmail.com', NULL, '020102050404', '$2y$10$vSjWcw9bxb51CfZdKS6kl.dPEFxVw7R/tH4P.9hKwdSdy.mpFGocu', 'public', NULL, 'No', NULL, '2026-01-11 14:17:48', '2026-01-11 14:17:48', NULL),
(8, 'Mashitah Anuar', 'mashi@example.com', '', '020102050468', '$2y$10$MSEOLYc.jE5thOZOaMbuQ.BsDi7LEtX6.tOFTn/oefoVpbhqc1ZU.', 'public', NULL, 'No', NULL, '2026-01-11 15:49:57', '2026-01-11 16:09:30', NULL),

--
-- Indexes for dumped tables
--

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`asset_id`),
  ADD KEY `fk_assets_lab` (`lab_id`);

--
-- Indexes for table `clusters`
--
ALTER TABLE `clusters`
  ADD PRIMARY KEY (`cluster_id`);

--
-- Indexes for table `labs`
--
ALTER TABLE `labs`
  ADD PRIMARY KEY (`lab_id`),
  ADD KEY `fk_labs_cluster` (`cluster_id`),
  ADD KEY `fk_labs_supervisor` (`supervisor_id`);

--
-- Indexes for table `lab_bookings`
--
ALTER TABLE `lab_bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_lab_slot` (`lab_id`,`booking_date`,`time_slot`),
  ADD KEY `fk_booking_user` (`user_id`);

--
-- Indexes for table `lab_supervisor_labs`
--
ALTER TABLE `lab_supervisor_labs`
  ADD PRIMARY KEY (`lab_supervisor_lab_id`),
  ADD UNIQUE KEY `uniq_lab_supervisor_scope` (`user_id`,`lab_id`),
  ADD KEY `fk_lab_supervisor_lab` (`lab_id`);

--
-- Indexes for table `lab_reservations`
--
ALTER TABLE `lab_reservations`
  ADD PRIMARY KEY (`reservation_id`),
  ADD KEY `fk_reservation_booking` (`booking_id`),
  ADD KEY `fk_reservation_cluster` (`cluster_id`);

--
-- Indexes for table `reservation_chemicals`
--
ALTER TABLE `reservation_chemicals`
  ADD PRIMARY KEY (`chemical_id`),
  ADD KEY `fk_chemicals_reservation` (`reservation_id`);

--
-- Indexes for table `reservation_equipment`
--
ALTER TABLE `reservation_equipment`
  ADD PRIMARY KEY (`equipment_id`),
  ADD KEY `fk_equipment_reservation` (`reservation_id`);

--
-- Indexes for table `supervisors`
--
ALTER TABLE `supervisors`
  ADD PRIMARY KEY (`supervisor_id`),
  ADD UNIQUE KEY `uniq_cluster_supervisor` (`cluster_id`,`supervisor_name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `fk_users_cluster` (`cluster_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `asset_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `clusters`
--
ALTER TABLE `clusters`
  MODIFY `cluster_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `labs`
--
ALTER TABLE `labs`
  MODIFY `lab_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=159;

--
-- AUTO_INCREMENT for table `lab_bookings`
--
ALTER TABLE `lab_bookings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `lab_supervisor_labs`
--
ALTER TABLE `lab_supervisor_labs`
  MODIFY `lab_supervisor_lab_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lab_reservations`
--
ALTER TABLE `lab_reservations`
  MODIFY `reservation_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `reservation_chemicals`
--
ALTER TABLE `reservation_chemicals`
  MODIFY `chemical_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reservation_equipment`
--
ALTER TABLE `reservation_equipment`
  MODIFY `equipment_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supervisors`
--
ALTER TABLE `supervisors`
  MODIFY `supervisor_id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `fk_assets_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`lab_id`);

--
-- Constraints for table `labs`
--
ALTER TABLE `labs`
  ADD CONSTRAINT `fk_labs_cluster` FOREIGN KEY (`cluster_id`) REFERENCES `clusters` (`cluster_id`),
  ADD CONSTRAINT `fk_labs_supervisor` FOREIGN KEY (`supervisor_id`) REFERENCES `supervisors` (`supervisor_id`);

--
-- Constraints for table `lab_supervisor_labs`
--
ALTER TABLE `lab_supervisor_labs`
  ADD CONSTRAINT `fk_lab_supervisor_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`lab_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lab_supervisor_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `lab_bookings`
--
ALTER TABLE `lab_bookings`
  ADD CONSTRAINT `fk_booking_lab` FOREIGN KEY (`lab_id`) REFERENCES `labs` (`lab_id`),
  ADD CONSTRAINT `fk_booking_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `lab_reservations`
--
ALTER TABLE `lab_reservations`
  ADD CONSTRAINT `fk_reservation_booking` FOREIGN KEY (`booking_id`) REFERENCES `lab_bookings` (`id`),
  ADD CONSTRAINT `fk_reservation_cluster` FOREIGN KEY (`cluster_id`) REFERENCES `clusters` (`cluster_id`);

--
-- Constraints for table `reservation_chemicals`
--
ALTER TABLE `reservation_chemicals`
  ADD CONSTRAINT `fk_chemicals_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `lab_reservations` (`reservation_id`) ON DELETE CASCADE;

--
-- Constraints for table `reservation_equipment`
--
ALTER TABLE `reservation_equipment`
  ADD CONSTRAINT `fk_equipment_reservation` FOREIGN KEY (`reservation_id`) REFERENCES `lab_reservations` (`reservation_id`) ON DELETE CASCADE;

--
-- Constraints for table `supervisors`
--
ALTER TABLE `supervisors`
  ADD CONSTRAINT `fk_supervisors_cluster` FOREIGN KEY (`cluster_id`) REFERENCES `clusters` (`cluster_id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_cluster` FOREIGN KEY (`cluster_id`) REFERENCES `clusters` (`cluster_id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
