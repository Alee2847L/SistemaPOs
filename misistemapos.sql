-- phpMyAdmin SQL Dump
-- version 4.9.0.1
-- https://www.phpmyadmin.net/
--
-- Servidor: sql209.byetcluster.com
-- Tiempo de generación: 25-08-2026 a las 16:34:46
-- Versión del servidor: 11.4.12-MariaDB
-- Versión de PHP: 7.2.22

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `if0_42542842_misistemapos`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `arqueo_caja`
--

CREATE TABLE `arqueo_caja` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `monto_inicial` decimal(10,2) NOT NULL,
  `monto_final` decimal(10,2) DEFAULT NULL,
  `total_ventas` decimal(10,2) DEFAULT 0.00,
  `fecha_apertura` datetime NOT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `estado` enum('abierta','cerrada') DEFAULT 'abierta'
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cierres_caja`
--

CREATE TABLE `cierres_caja` (
  `id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `monto_inicial` decimal(10,2) DEFAULT 0.00,
  `total_ventas` decimal(10,2) NOT NULL,
  `cantidad_transacciones` int(11) NOT NULL,
  `fecha_cierre` datetime NOT NULL,
  `detalle_transacciones` text DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `cierres_caja`
--

INSERT INTO `cierres_caja` (`id`, `usuario_id`, `monto_inicial`, `total_ventas`, `cantidad_transacciones`, `fecha_cierre`, `detalle_transacciones`) VALUES
(1, 1, '0.00', '10725.00', 4, '2026-08-13 23:56:36', '[{\"id_transaccion\":5,\"cliente_identidad\":\"BP005\",\"total\":\"1500.00\",\"fecha_venta\":\"2026-08-13 23:21:03\",\"cajero\":\"Administrador\",\"pagos\":\"Efectivo: L.3000.00\"},{\"id_transaccion\":4,\"cliente_identidad\":\"BP006\",\"total\":\"4500.00\",\"fecha_venta\":\"2026-08-13 23:15:19\",\"cajero\":\"Administrador\",\"pagos\":\"Efectivo: L.6000.00\"},{\"id_transaccion\":3,\"cliente_identidad\":\"BP005\",\"total\":\"3000.00\",\"fecha_venta\":\"2026-08-13 23:12:30\",\"cajero\":\"Administrador\",\"pagos\":\"Efectivo: L.4000.00\"},{\"id_transaccion\":2,\"cliente_identidad\":\"BP007\",\"total\":\"1725.00\",\"fecha_venta\":\"2026-08-13 22:59:54\",\"cajero\":\"Administrador\",\"pagos\":\"Efectivo: L.2000.00\"}]'),
(2, 1, '0.00', '10725.00', 4, '2026-08-13 23:58:14', '[{\"id_transaccion\":5,\"cliente_identidad\":\"BP005\",\"total\":\"1500.00\",\"fecha_venta\":\"2026-08-13 23:21:03\",\"cajero\":\"Administrador\",\"pagos\":\"Efectivo: L.3000.00\"},{\"id_transaccion\":4,\"cliente_identidad\":\"BP006\",\"total\":\"4500.00\",\"fecha_venta\":\"2026-08-13 23:15:19\",\"cajero\":\"Administrador\",\"pagos\":\"Efectivo: L.6000.00\"},{\"id_transaccion\":3,\"cliente_identidad\":\"BP005\",\"total\":\"3000.00\",\"fecha_venta\":\"2026-08-13 23:12:30\",\"cajero\":\"Administrador\",\"pagos\":\"Efectivo: L.4000.00\"},{\"id_transaccion\":2,\"cliente_identidad\":\"BP007\",\"total\":\"1725.00\",\"fecha_venta\":\"2026-08-13 22:59:54\",\"cajero\":\"Administrador\",\"pagos\":\"Efectivo: L.2000.00\"}]'),
(3, 6, '0.00', '9000.00', 3, '2026-08-14 06:18:47', '[{\"id_transaccion\":8,\"cliente_identidad\":\"BP008\",\"total\":\"4500.00\",\"fecha_venta\":\"2026-08-14 06:16:37\",\"cajero\":\"Joseph Alejandro Mayorga\",\"pagos\":\"Efectivo: L.6000.00\"},{\"id_transaccion\":7,\"cliente_identidad\":\"BP007\",\"total\":\"1500.00\",\"fecha_venta\":\"2026-08-14 00:21:16\",\"cajero\":\"Joseph Alejandro Mayorga\",\"pagos\":\"Efectivo: L.2000.00\"},{\"id_transaccion\":6,\"cliente_identidad\":\"BP007\",\"total\":\"3000.00\",\"fecha_venta\":\"2026-08-13 23:58:51\",\"cajero\":\"Administrador\",\"pagos\":\"Tarjeta: L.450.00, Efectivo: L.3000.00\"}]'),
(4, 6, '0.00', '11298.00', 2, '2026-08-17 19:09:47', '[{\"id_transaccion\":10,\"cliente_identidad\":\"BP010\",\"total\":\"9798.00\",\"fecha_venta\":\"2026-08-17 19:07:28\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":\"Efectivo: L.7000.00, Tarjeta: L.5000.00\"},{\"id_transaccion\":9,\"cliente_identidad\":\"BP007\",\"total\":\"1500.00\",\"fecha_venta\":\"2026-08-17 11:30:59\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":\"Efectivo: L.1725.00, Efectivo: L.1500.00\"}]'),
(5, 6, '0.00', '19931.50', 11, '2026-08-18 01:25:06', '[{\"id_transaccion\":21,\"cliente_identidad\":\"\",\"total\":\"500.00\",\"fecha_venta\":\"2026-08-18 01:23:23\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":null},{\"id_transaccion\":20,\"cliente_identidad\":\"\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-18 01:15:09\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":null},{\"id_transaccion\":19,\"cliente_identidad\":\"\",\"total\":\"332.50\",\"fecha_venta\":\"2026-08-18 01:13:59\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":null},{\"id_transaccion\":18,\"cliente_identidad\":\"\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-18 00:28:25\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":null},{\"id_transaccion\":17,\"cliente_identidad\":\"\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-18 00:26:36\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":null},{\"id_transaccion\":16,\"cliente_identidad\":\"\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-18 00:23:48\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":null},{\"id_transaccion\":15,\"cliente_identidad\":\"BP005\",\"total\":\"1850.00\",\"fecha_venta\":\"2026-08-18 00:01:22\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":\"Efectivo: L.2000.00\"},{\"id_transaccion\":14,\"cliente_identidad\":\"BP007\",\"total\":\"7850.00\",\"fecha_venta\":\"2026-08-17 23:59:44\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":\"Efectivo: L.8000.00\"},{\"id_transaccion\":13,\"cliente_identidad\":\"BP008\",\"total\":\"1550.00\",\"fecha_venta\":\"2026-08-17 23:58:22\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":\"Efectivo: L.1500.00\"},{\"id_transaccion\":12,\"cliente_identidad\":\"BP005\",\"total\":\"1200.00\",\"fecha_venta\":\"2026-08-17 23:48:44\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":\"Efectivo: L.2000.00\"},{\"id_transaccion\":11,\"cliente_identidad\":\"BP006\",\"total\":\"5249.00\",\"fecha_venta\":\"2026-08-17 21:38:39\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":\"Efectivo: L.6000.00\"}]'),
(6, 6, '0.00', '320.00', 1, '2026-08-18 08:30:33', '[{\"id_transaccion\":22,\"cliente_identidad\":\"\",\"total\":\"320.00\",\"fecha_venta\":\"2026-08-18 08:28:43\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":null}]'),
(7, 6, '0.00', '13900.00', 2, '2026-08-18 22:07:29', '[{\"id_transaccion\":24,\"cliente_identidad\":\"\",\"total\":\"1500.00\",\"fecha_venta\":\"2026-08-18 21:28:51\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":null},{\"id_transaccion\":23,\"cliente_identidad\":\"\",\"total\":\"12400.00\",\"fecha_venta\":\"2026-08-18 21:11:26\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":null}]'),
(8, 6, '0.00', '1500.00', 1, '2026-08-18 22:56:18', '[{\"id_transaccion\":25,\"cliente_identidad\":\"\",\"total\":\"1500.00\",\"fecha_venta\":\"2026-08-18 22:54:14\",\"cajero\":\"Bryan Alejandro Mayorga\",\"pagos\":null}]'),
(9, 10, '0.00', '1000.00', 2, '2026-08-19 13:04:18', '[{\"id_transaccion\":27,\"cliente_identidad\":\"\",\"total\":\"500.00\",\"fecha_venta\":\"2026-08-19 13:03:04\",\"cajero\":\"Administrador Prueba\",\"pagos\":null},{\"id_transaccion\":26,\"cliente_identidad\":\"\",\"total\":\"500.00\",\"fecha_venta\":\"2026-08-19 13:02:28\",\"cajero\":\"Administrador Prueba\",\"pagos\":null}]'),
(10, 10, '0.00', '350.00', 1, '2026-08-19 13:33:22', '[{\"id_transaccion\":28,\"cliente_codigo_bp\":\"BP005\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-19 13:13:49\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"150.00\",\"cajero\":\"Administrador Prueba\"}]'),
(11, 10, '0.00', '-700.00', 4, '2026-08-19 14:38:29', '[{\"id_transaccion\":32,\"cliente_codigo_bp\":\"BP005\",\"total\":\"-350.00\",\"fecha_venta\":\"2026-08-19 14:34:41\",\"metodo_pago\":\"Devoluci\\u00f3n por: Producto Defectuoso - cliente\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":31,\"cliente_codigo_bp\":\"BP005\",\"total\":\"-350.00\",\"fecha_venta\":\"2026-08-19 14:30:54\",\"metodo_pago\":\"Devoluci\\u00f3n por: Producto Defectuoso - cliente no esta satisfecho\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":30,\"cliente_codigo_bp\":\"BP006\",\"total\":\"-350.00\",\"fecha_venta\":\"2026-08-19 14:26:35\",\"metodo_pago\":\"Devoluci\\u00f3n por: Producto Defectuoso - cliente insatisfecho\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":29,\"cliente_codigo_bp\":\"BP006\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-19 13:34:36\",\"metodo_pago\":\"Tarjeta\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Administrador Prueba\"}]'),
(12, 10, '0.00', '18170.50', 23, '2026-08-21 12:33:15', '[{\"id_transaccion\":55,\"cliente_codigo_bp\":\"BP005\",\"total\":\"5000.00\",\"fecha_venta\":\"2026-08-21 12:30:38\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":54,\"cliente_codigo_bp\":\"BP005\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-21 12:29:41\",\"metodo_pago\":\"Pendiente\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":53,\"cliente_codigo_bp\":\"BP007\",\"total\":\"732.50\",\"fecha_venta\":\"2026-08-21 12:26:58\",\"metodo_pago\":\"Efectivo \\/ Tarjeta\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":52,\"cliente_codigo_bp\":\"BP005\",\"total\":\"-350.00\",\"fecha_venta\":\"2026-08-20 16:28:25\",\"metodo_pago\":\"Devoluci\\u00f3n por: Error de Cobro en Caja - cliente no desea continuar\",\"cambio_entregado\":\"350.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id_transaccion\":51,\"cliente_codigo_bp\":\"BP005\",\"total\":\"2798.00\",\"fecha_venta\":\"2026-08-20 16:24:06\",\"metodo_pago\":\"Tarjeta\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id_transaccion\":50,\"cliente_codigo_bp\":\"BP000\",\"total\":\"2798.00\",\"fecha_venta\":\"2026-08-20 16:06:27\",\"metodo_pago\":\"Pendiente\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id_transaccion\":49,\"cliente_codigo_bp\":\"BP005\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-20 15:57:17\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"150.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id_transaccion\":48,\"cliente_codigo_bp\":\"BP005\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-20 15:50:03\",\"metodo_pago\":\"Pendiente\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id_transaccion\":47,\"cliente_codigo_bp\":\"BP009\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-20 15:47:42\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"150.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id_transaccion\":46,\"cliente_codigo_bp\":\"BP006\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-20 15:33:19\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"150.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id_transaccion\":45,\"cliente_codigo_bp\":\"BP006\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-20 15:32:57\",\"metodo_pago\":\"Pendiente\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id_transaccion\":44,\"cliente_codigo_bp\":\"BP007\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-20 15:24:02\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"150.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id_transaccion\":43,\"cliente_codigo_bp\":\"BP007\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-20 15:23:37\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"150.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id_transaccion\":42,\"cliente_codigo_bp\":\"BP005\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-20 15:07:49\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"150.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id_transaccion\":41,\"cliente_codigo_bp\":\"BP005\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-20 14:25:07\",\"metodo_pago\":\"Pendiente\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id_transaccion\":40,\"cliente_codigo_bp\":\"BP005\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-20 14:05:51\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"150.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id_transaccion\":39,\"cliente_codigo_bp\":\"BP000\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-20 14:01:09\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"150.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id_transaccion\":38,\"cliente_codigo_bp\":\"BP000\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-20 13:46:23\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"150.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id_transaccion\":37,\"cliente_codigo_bp\":\"BP005\",\"total\":\"790.00\",\"fecha_venta\":\"2026-08-19 20:35:59\",\"metodo_pago\":\"Efectivo \\/ Tarjeta\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":36,\"cliente_codigo_bp\":\"BP008\",\"total\":\"15000.00\",\"fecha_venta\":\"2026-08-19 15:03:57\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"5000.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":35,\"cliente_codigo_bp\":\"BP000\",\"total\":\"-3000.00\",\"fecha_venta\":\"2026-08-19 15:02:43\",\"metodo_pago\":\"Devoluci\\u00f3n por: Error de Cobro en Caja - mal cambio al cliente\",\"cambio_entregado\":\"3000.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":34,\"cliente_codigo_bp\":\"BP000\",\"total\":\"-9798.00\",\"fecha_venta\":\"2026-08-19 14:49:54\",\"metodo_pago\":\"Devoluci\\u00f3n por: Producto Defectuoso - cliente insatisfecho\",\"cambio_entregado\":\"9798.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":33,\"cliente_codigo_bp\":\"BP008\",\"total\":\"-350.00\",\"fecha_venta\":\"2026-08-19 14:46:30\",\"metodo_pago\":\"Devoluci\\u00f3n por: Producto Defectuoso - cliente insatisfecho\",\"cambio_entregado\":\"350.00\",\"cajero\":\"Administrador Prueba\"}]'),
(13, 10, '0.00', '0.00', 4, '2026-08-22 13:32:27', '[{\"id_transaccion\":58,\"cliente_codigo_bp\":\"BP008\",\"total\":\"6650.00\",\"fecha_venta\":\"2026-08-22 13:18:31\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"350.00\",\"cajero\":\"Vendedor Prueba\"},{\"id_transaccion\":59,\"cliente_codigo_bp\":\"BP006\",\"total\":\"435.00\",\"fecha_venta\":\"2026-08-22 12:50:58\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"65.00\",\"cajero\":\"Vendedor Prueba\"},{\"id_transaccion\":57,\"cliente_codigo_bp\":\"BP009\",\"total\":\"350.00\",\"fecha_venta\":\"2026-08-22 12:39:08\",\"metodo_pago\":\"Efectivo \\/ Tarjeta\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Vendedor Prueba\"},{\"id_transaccion\":56,\"cliente_codigo_bp\":\"BP005\",\"total\":\"-5000.00\",\"fecha_venta\":\"2026-08-21 12:34:02\",\"metodo_pago\":\"Devoluci\\u00f3n por: Producto Defectuoso - Producto con desperfectos de fabrica\",\"cambio_entregado\":\"5000.00\",\"cajero\":\"Administrador Prueba\"}]'),
(14, 10, '0.00', '10550.00', 8, '2026-08-22 14:48:22', '[{\"id_transaccion\":67,\"cliente_codigo_bp\":\"BP005\",\"total\":\"350.00\",\"monto_efectivo\":\"350.00\",\"monto_tarjeta\":\"0.00\",\"fecha_venta\":\"2026-08-22 14:46:37\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"50.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":66,\"cliente_codigo_bp\":\"BP000\",\"total\":\"700.00\",\"monto_efectivo\":\"800.00\",\"monto_tarjeta\":\"0.00\",\"fecha_venta\":\"2026-08-22 14:25:41\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"100.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":65,\"cliente_codigo_bp\":\"BP006\",\"total\":\"350.00\",\"monto_efectivo\":\"500.00\",\"monto_tarjeta\":\"0.00\",\"fecha_venta\":\"2026-08-22 14:18:29\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"150.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":64,\"cliente_codigo_bp\":\"BP005\",\"total\":\"350.00\",\"monto_efectivo\":\"500.00\",\"monto_tarjeta\":\"0.00\",\"fecha_venta\":\"2026-08-22 14:10:21\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"150.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":63,\"cliente_codigo_bp\":\"BP005\",\"total\":\"350.00\",\"monto_efectivo\":\"350.00\",\"monto_tarjeta\":\"0.00\",\"fecha_venta\":\"2026-08-22 14:08:17\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"150.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":62,\"cliente_codigo_bp\":\"BP007\",\"total\":\"500.00\",\"monto_efectivo\":\"300.00\",\"monto_tarjeta\":\"250.00\",\"fecha_venta\":\"2026-08-22 13:49:39\",\"metodo_pago\":\"Tarjeta \\/ Efectivo\",\"cambio_entregado\":\"50.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":61,\"cliente_codigo_bp\":\"BP005\",\"total\":\"500.00\",\"monto_efectivo\":\"0.00\",\"monto_tarjeta\":\"500.00\",\"fecha_venta\":\"2026-08-22 13:48:52\",\"metodo_pago\":\"Tarjeta\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":60,\"cliente_codigo_bp\":\"BP005\",\"total\":\"7000.00\",\"monto_efectivo\":\"4000.00\",\"monto_tarjeta\":\"3000.00\",\"fecha_venta\":\"2026-08-22 13:33:40\",\"metodo_pago\":\"Efectivo \\/ Tarjeta\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Administrador Prueba\"}]'),
(15, 10, '0.00', '7000.00', 1, '2026-08-22 14:52:14', '[{\"id_transaccion\":68,\"cliente_codigo_bp\":\"BP006\",\"total\":\"7000.00\",\"monto_efectivo\":\"7000.00\",\"monto_tarjeta\":\"0.00\",\"fecha_venta\":\"2026-08-22 14:51:48\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"1000.00\",\"cajero\":\"Administrador Prueba\"}]'),
(16, 10, '0.00', '500.00', 1, '2026-08-22 14:54:51', '[{\"id_transaccion\":69,\"cliente_codigo_bp\":\"BP000\",\"total\":\"500.00\",\"monto_efectivo\":\"500.00\",\"monto_tarjeta\":\"0.00\",\"fecha_venta\":\"2026-08-22 14:53:57\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"100.00\",\"cajero\":\"Vendedor Prueba\"}]'),
(17, 10, '0.00', '7000.00', 1, '2026-08-22 20:11:54', '[{\"id_transaccion\":70,\"cliente_codigo_bp\":\"BP014\",\"total\":\"7000.00\",\"monto_efectivo\":\"5000.00\",\"monto_tarjeta\":\"2000.00\",\"fecha_venta\":\"2026-08-22 20:10:01\",\"metodo_pago\":\"Efectivo \\/ Tarjeta\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Administrador Prueba\"}]'),
(18, 10, '0.00', '3350.00', 3, '2026-08-23 20:33:06', '[{\"id_transaccion\":73,\"cliente_codigo_bp\":\"BP015\",\"total\":\"500.00\",\"monto_efectivo\":\"0.00\",\"monto_tarjeta\":\"500.00\",\"fecha_venta\":\"2026-08-23 20:31:30\",\"metodo_pago\":\"Tarjeta\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":72,\"cliente_codigo_bp\":\"BP005\",\"total\":\"1350.00\",\"monto_efectivo\":\"500.00\",\"monto_tarjeta\":\"850.00\",\"fecha_venta\":\"2026-08-23 20:28:13\",\"metodo_pago\":\"Efectivo \\/ Tarjeta\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Administrador Prueba\"},{\"id_transaccion\":71,\"cliente_codigo_bp\":\"BP014\",\"total\":\"-7000.00\",\"monto_efectivo\":\"0.00\",\"monto_tarjeta\":\"0.00\",\"fecha_venta\":\"2026-08-22 20:13:39\",\"metodo_pago\":\"Devoluci\\u00f3n por: Producto Defectuoso - cliente se da de baja\",\"cambio_entregado\":\"7000.00\",\"cajero\":\"Administrador Prueba\"}]');
INSERT INTO `cierres_caja` (`id`, `usuario_id`, `monto_inicial`, `total_ventas`, `cantidad_transacciones`, `fecha_cierre`, `detalle_transacciones`) VALUES
(19, 6, '0.00', '0.00', 12, '2026-08-24 20:46:11', '[{\"id\":74,\"tipo\":\"VENTA\",\"cliente_codigo_bp\":\"BP006\",\"total\":\"-500.00\",\"monto_efectivo\":\"0.00\",\"monto_tarjeta\":\"0.00\",\"fecha\":\"2026-08-23 20:33:48\",\"metodo_pago\":\"Devoluci\\u00f3n por: Producto Defectuoso - Producto sali\\u00f3 quebrado, se facturar\\u00e1 uno nuevo\",\"cambio_entregado\":\"500.00\",\"cajero\":\"Administrador Prueba\"},{\"id\":75,\"tipo\":\"VENTA\",\"cliente_codigo_bp\":\"BP006\",\"total\":\"500.00\",\"monto_efectivo\":\"500.00\",\"monto_tarjeta\":\"0.00\",\"fecha\":\"2026-08-23 20:34:41\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Administrador Prueba\"},{\"id\":76,\"tipo\":\"VENTA\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"7000.00\",\"monto_efectivo\":\"1000.00\",\"monto_tarjeta\":\"0.00\",\"fecha\":\"2026-08-24 13:13:36\",\"metodo_pago\":\"Efectivo\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":77,\"tipo\":\"VENTA\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"7000.00\",\"monto_efectivo\":\"1000.00\",\"monto_tarjeta\":\"0.00\",\"fecha\":\"2026-08-24 13:23:43\",\"metodo_pago\":\"Cr\\u00e9dito \\/ Efectivo\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":78,\"tipo\":\"VENTA\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"7000.00\",\"monto_efectivo\":\"1000.00\",\"monto_tarjeta\":\"0.00\",\"fecha\":\"2026-08-24 13:45:13\",\"metodo_pago\":\"Cr\\u00e9dito \\/ Efectivo\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":80,\"tipo\":\"VENTA\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"7000.00\",\"monto_efectivo\":\"2000.00\",\"monto_tarjeta\":\"0.00\",\"fecha\":\"2026-08-24 14:00:32\",\"metodo_pago\":\"Cr\\u00e9dito \\/ Efectivo\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":1,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"937.50\",\"monto_efectivo\":\"937.50\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 20:12:57\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":null},{\"id\":2,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"937.50\",\"monto_efectivo\":\"937.50\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 20:12:57\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":null},{\"id\":3,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"937.50\",\"monto_efectivo\":\"937.50\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 20:14:22\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":null},{\"id\":4,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"937.50\",\"monto_efectivo\":\"937.50\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 20:14:22\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":null},{\"id\":5,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"937.50\",\"monto_efectivo\":\"937.50\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 20:14:22\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":null},{\"id\":6,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"937.50\",\"monto_efectivo\":\"937.50\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 20:14:22\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":null}]'),
(20, 6, '0.00', '0.00', 2, '2026-08-24 20:58:41', '[{\"id\":81,\"tipo\":\"VENTA\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"7000.00\",\"monto_efectivo\":\"0.00\",\"monto_tarjeta\":\"0.00\",\"fecha\":\"2026-08-24 20:52:41\",\"metodo_pago\":\"Cr\\u00e9dito\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":82,\"tipo\":\"VENTA\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"1850.00\",\"monto_efectivo\":\"0.00\",\"monto_tarjeta\":\"0.00\",\"fecha\":\"2026-08-24 20:57:56\",\"metodo_pago\":\"Cr\\u00e9dito\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Bryan Alejandro Mayorga\"}]'),
(21, 6, '0.00', '0.00', 14, '2026-08-24 21:31:58', '[{\"id\":83,\"tipo\":\"VENTA\",\"cliente_codigo_bp\":\"BP014\",\"total\":\"9994.00\",\"monto_efectivo\":\"0.00\",\"monto_tarjeta\":\"0.00\",\"fecha\":\"2026-08-24 21:26:48\",\"metodo_pago\":\"Cr\\u00e9dito\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Vendedor Prueba\"},{\"id\":7,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"729.17\",\"monto_efectivo\":\"729.17\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 21:13:31\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":8,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"729.17\",\"monto_efectivo\":\"729.17\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 21:13:31\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":9,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"729.17\",\"monto_efectivo\":\"729.17\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 21:13:31\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":10,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"729.17\",\"monto_efectivo\":\"729.17\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 21:13:31\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":11,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"729.17\",\"monto_efectivo\":\"729.17\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 21:13:31\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":12,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"729.17\",\"monto_efectivo\":\"729.17\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 21:13:31\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":13,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"729.17\",\"monto_efectivo\":\"729.17\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 21:13:31\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":14,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"729.17\",\"monto_efectivo\":\"729.17\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 21:13:31\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":15,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"729.17\",\"monto_efectivo\":\"729.17\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 21:13:31\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":16,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"729.17\",\"monto_efectivo\":\"729.17\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 21:13:31\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":17,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"729.17\",\"monto_efectivo\":\"729.17\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 21:13:31\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":18,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP005\",\"total\":\"729.17\",\"monto_efectivo\":\"729.17\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 21:13:31\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":31,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP014\",\"total\":\"763.43\",\"monto_efectivo\":\"763.43\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-24 21:29:25\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Vendedor Prueba\"}]'),
(22, 6, '0.00', '0.00', 15, '2026-08-25 13:30:50', '[{\"id\":84,\"tipo\":\"VENTA\",\"cliente_codigo_bp\":\"BP006\",\"total\":\"19988.00\",\"monto_efectivo\":\"0.00\",\"monto_tarjeta\":\"0.00\",\"fecha\":\"2026-08-25 12:55:40\",\"metodo_pago\":\"Cr\\u00e9dito\",\"cambio_entregado\":\"0.00\",\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":32,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP014\",\"total\":\"763.43\",\"monto_efectivo\":\"763.43\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-25 12:03:08\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":33,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP014\",\"total\":\"763.43\",\"monto_efectivo\":\"763.43\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-25 12:04:50\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Vendedor Prueba\"},{\"id\":49,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP006\",\"total\":\"2082.08\",\"monto_efectivo\":\"2082.08\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-25 13:07:28\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":50,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP006\",\"total\":\"2082.08\",\"monto_efectivo\":\"2082.08\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-25 13:07:28\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":51,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP006\",\"total\":\"2082.08\",\"monto_efectivo\":\"2082.08\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-25 13:07:28\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":52,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP006\",\"total\":\"2082.08\",\"monto_efectivo\":\"2082.08\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-25 13:07:28\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":53,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP006\",\"total\":\"2082.08\",\"monto_efectivo\":\"2082.08\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-25 13:07:28\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":54,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP006\",\"total\":\"2082.08\",\"monto_efectivo\":\"2082.08\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-25 13:07:28\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":55,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP006\",\"total\":\"2082.08\",\"monto_efectivo\":\"2082.08\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-25 13:07:28\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":56,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP006\",\"total\":\"2082.08\",\"monto_efectivo\":\"2082.08\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-25 13:07:28\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":57,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP006\",\"total\":\"2082.08\",\"monto_efectivo\":\"2082.08\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-25 13:07:28\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":58,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP006\",\"total\":\"2082.08\",\"monto_efectivo\":\"2082.08\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-25 13:07:28\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":59,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP006\",\"total\":\"2082.08\",\"monto_efectivo\":\"2082.08\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-25 13:07:28\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"},{\"id\":60,\"tipo\":\"RECAUDO\",\"cliente_codigo_bp\":\"BP006\",\"total\":\"2082.08\",\"monto_efectivo\":\"2082.08\",\"monto_tarjeta\":0,\"fecha\":\"2026-08-25 13:07:28\",\"metodo_pago\":\"EFECTIVO\",\"cambio_entregado\":0,\"cajero\":\"Bryan Alejandro Mayorga\"}]');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `codigo_bp` varchar(20) DEFAULT NULL,
  `tipo_cliente` enum('natural','juridico') NOT NULL DEFAULT 'natural',
  `rtn_dni` varchar(30) NOT NULL,
  `Nombre` varchar(100) NOT NULL,
  `Telefono` varchar(20) DEFAULT NULL,
  `Direccion` text DEFAULT NULL,
  `Correo` varchar(100) DEFAULT NULL,
  `limite_credito` decimal(10,2) DEFAULT 0.00,
  `dias_credito` int(11) DEFAULT 0,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `estado` enum('ACT','INA') NOT NULL DEFAULT 'ACT'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`codigo_bp`, `tipo_cliente`, `rtn_dni`, `Nombre`, `Telefono`, `Direccion`, `Correo`, `limite_credito`, `dias_credito`, `fecha_creacion`, `estado`) VALUES
('BP011', 'natural', '004004004004004', 'casa de la cultura', '9898999', '', '', '0.00', 0, '2026-08-18 04:15:25', 'INA'),
('BP005', 'natural', '0301198800123', 'María Fernanda López', '8811-2236', 'Comayagua', 'maria.lopez@email.com', '48900.04', 0, '2026-08-14 05:03:19', 'ACT'),
('BP008', 'juridico', '03181999007104', 'Inversiones JA', '97813880', 'calle principal', 'aleebryanmayorga@gmail.com', '0.00', 0, '2026-08-14 05:09:07', 'INA'),
('BP014', 'natural', '0318199900806', 'Betany Gisselle Ruiz Reyes', '9652-2805', '', '', '992296.29', 0, '2026-08-23 03:07:10', 'ACT'),
('BP009', 'juridico', '031888889129381', 'casa de emepelo', '99889988', '', '', '0.00', 0, '2026-08-14 05:14:32', 'INA'),
('BP015', 'juridico', '0318999900657', 'Almacenes Nuevos', '9898-0009', 'Bo el centro', 'almacenes@gmail.com', '0.00', 0, '2026-08-24 03:30:46', 'INA'),
('BP013', 'natural', '0501-1988-000009', 'Carlos Eduardo espina', '99009900', 'calle pral', 'carlos@gmail.com', '0.00', 0, '2026-08-19 05:53:47', 'INA'),
('BP016', 'natural', '05011998000989', 'Carlos Armando Castillo', '88998899', '', '', '0.00', 0, '2026-08-25 20:25:14', 'INA'),
('BP006', 'juridico', '05019015896321', 'Distribuidora del Sur S.A.', '2772-1100', 'Choluteca', 'ventas@distsur.hn', '24984.96', 0, '2026-08-14 05:03:19', 'ACT'),
('BP007', 'natural', '1201200000456', 'Roberto Gómez', '9500-4411', 'La Ceiba', 'roberto.gomez@email.com', '0.00', 0, '2026-08-14 05:03:19', 'INA'),
('BP012', 'natural', '88997867867', 'Carlos zamora', '9989899', 'asdas', 'carlos@gmail.com', '0.00', 0, '2026-08-18 08:22:05', 'INA');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `configuracion`
--

CREATE TABLE `configuracion` (
  `id` int(11) NOT NULL,
  `nombre_empresa` varchar(100) NOT NULL,
  `rtn` varchar(30) NOT NULL,
  `direccion` text NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `cai` varchar(50) NOT NULL,
  `prefijo_factura` varchar(20) NOT NULL,
  `siguiente_correlativo` int(11) NOT NULL DEFAULT 1,
  `rango_maximo` int(11) NOT NULL DEFAULT 0,
  `rango_autorizado` varchar(100) NOT NULL,
  `fecha_limite` date NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `configuracion`
--

INSERT INTO `configuracion` (`id`, `nombre_empresa`, `rtn`, `direccion`, `telefono`, `cai`, `prefijo_factura`, `siguiente_correlativo`, `rango_maximo`, `rango_autorizado`, `fecha_limite`) VALUES
(1, 'INVERSIONES J.A', '03181999007104', 'aguas del padre', '96522805', '0090899899', '000-001-01-', 38, 100, '001-01-01-00001', '2040-12-31');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `contratos`
--

CREATE TABLE `contratos` (
  `id` int(11) NOT NULL,
  `codigo_bp` varchar(20) NOT NULL,
  `producto_descripcion` text NOT NULL,
  `total_factura` decimal(10,2) NOT NULL,
  `prima` decimal(10,2) NOT NULL DEFAULT 0.00,
  `monto_financiar` decimal(10,2) NOT NULL,
  `porcentaje_interes` decimal(5,2) NOT NULL DEFAULT 0.00,
  `total_credito` decimal(10,2) NOT NULL,
  `plazo_meses` int(11) NOT NULL,
  `fecha_inicio` date NOT NULL,
  `estado` enum('ACTIVO','FINALIZADO','CANCELADO') DEFAULT 'ACTIVO',
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `contratos`
--

INSERT INTO `contratos` (`id`, `codigo_bp`, `producto_descripcion`, `total_factura`, `prima`, `monto_financiar`, `porcentaje_interes`, `total_credito`, `plazo_meses`, `fecha_inicio`, `estado`, `fecha_creacion`) VALUES
(1, 'BP005', '1x silla gaming', '7000.00', '2000.00', '5000.00', '0.00', '5625.00', 6, '2026-08-24', 'FINALIZADO', '2026-08-24 21:00:32'),
(2, 'BP005', '1x silla gaming', '7000.00', '0.00', '7000.00', '0.00', '8750.00', 12, '2026-08-24', 'FINALIZADO', '2026-08-25 03:52:41'),
(3, 'BP005', '1x Mouse Inalámbrico, 1x Camisa embarazada', '1850.00', '0.00', '1850.00', '0.00', '2312.50', 12, '2026-08-24', 'ACTIVO', '2026-08-25 03:57:56'),
(4, 'BP014', '1x Parlante JBL extreme', '9994.00', '0.00', '9994.00', '0.00', '13741.75', 18, '2026-08-25', 'ACTIVO', '2026-08-25 04:26:48'),
(5, 'BP006', '2x Parlante JBL extreme', '19988.00', '0.00', '19988.00', '0.00', '24985.00', 12, '2026-08-25', 'FINALIZADO', '2026-08-25 19:55:40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cuotas_contrato`
--

CREATE TABLE `cuotas_contrato` (
  `id` int(11) NOT NULL,
  `contrato_id` int(11) NOT NULL,
  `numero_cuota` int(11) NOT NULL,
  `monto_cuota` decimal(10,2) NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `monto_pagado` decimal(10,2) DEFAULT 0.00,
  `fecha_pago` datetime DEFAULT NULL,
  `estado` enum('PENDIENTE','PAGADO','VENCIDO') DEFAULT 'PENDIENTE',
  `estado_caja` varchar(20) DEFAULT 'abierta',
  `usuario_id` int(11) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Volcado de datos para la tabla `cuotas_contrato`
--

INSERT INTO `cuotas_contrato` (`id`, `contrato_id`, `numero_cuota`, `monto_cuota`, `fecha_vencimiento`, `monto_pagado`, `fecha_pago`, `estado`, `estado_caja`, `usuario_id`) VALUES
(1, 1, 1, '937.50', '2026-09-24', '937.50', '2026-08-24 20:12:57', 'PAGADO', 'cerrada', NULL),
(2, 1, 2, '937.50', '2026-10-24', '937.50', '2026-08-24 20:12:57', 'PAGADO', 'cerrada', NULL),
(3, 1, 3, '937.50', '2026-11-24', '937.50', '2026-08-24 20:14:22', 'PAGADO', 'cerrada', NULL),
(4, 1, 4, '937.50', '2026-12-24', '937.50', '2026-08-24 20:14:22', 'PAGADO', 'cerrada', NULL),
(5, 1, 5, '937.50', '2027-01-24', '937.50', '2026-08-24 20:14:22', 'PAGADO', 'cerrada', NULL),
(6, 1, 6, '937.50', '2027-02-24', '937.50', '2026-08-24 20:14:22', 'PAGADO', 'cerrada', NULL),
(7, 2, 1, '729.17', '2026-09-24', '729.17', '2026-08-24 21:13:31', 'PAGADO', 'cerrada', 6),
(8, 2, 2, '729.17', '2026-10-24', '729.17', '2026-08-24 21:13:31', 'PAGADO', 'cerrada', 6),
(9, 2, 3, '729.17', '2026-11-24', '729.17', '2026-08-24 21:13:31', 'PAGADO', 'cerrada', 6),
(10, 2, 4, '729.17', '2026-12-24', '729.17', '2026-08-24 21:13:31', 'PAGADO', 'cerrada', 6),
(11, 2, 5, '729.17', '2027-01-24', '729.17', '2026-08-24 21:13:31', 'PAGADO', 'cerrada', 6),
(12, 2, 6, '729.17', '2027-02-24', '729.17', '2026-08-24 21:13:31', 'PAGADO', 'cerrada', 6),
(13, 2, 7, '729.17', '2027-03-24', '729.17', '2026-08-24 21:13:31', 'PAGADO', 'cerrada', 6),
(14, 2, 8, '729.17', '2027-04-24', '729.17', '2026-08-24 21:13:31', 'PAGADO', 'cerrada', 6),
(15, 2, 9, '729.17', '2027-05-24', '729.17', '2026-08-24 21:13:31', 'PAGADO', 'cerrada', 6),
(16, 2, 10, '729.17', '2027-06-24', '729.17', '2026-08-24 21:13:31', 'PAGADO', 'cerrada', 6),
(17, 2, 11, '729.17', '2027-07-24', '729.17', '2026-08-24 21:13:31', 'PAGADO', 'cerrada', 6),
(18, 2, 12, '729.17', '2027-08-24', '729.17', '2026-08-24 21:13:31', 'PAGADO', 'cerrada', 6),
(19, 3, 1, '192.71', '2026-09-24', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(20, 3, 2, '192.71', '2026-10-24', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(21, 3, 3, '192.71', '2026-11-24', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(22, 3, 4, '192.71', '2026-12-24', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(23, 3, 5, '192.71', '2027-01-24', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(24, 3, 6, '192.71', '2027-02-24', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(25, 3, 7, '192.71', '2027-03-24', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(26, 3, 8, '192.71', '2027-04-24', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(27, 3, 9, '192.71', '2027-05-24', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(28, 3, 10, '192.71', '2027-06-24', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(29, 3, 11, '192.71', '2027-07-24', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(30, 3, 12, '192.71', '2027-08-24', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(31, 4, 1, '763.43', '2026-09-25', '763.43', '2026-08-24 21:29:25', 'PAGADO', 'cerrada', 11),
(32, 4, 2, '763.43', '2026-10-25', '763.43', '2026-08-25 12:03:08', 'PAGADO', 'cerrada', 6),
(33, 4, 3, '763.43', '2026-11-25', '763.43', '2026-08-25 12:04:50', 'PAGADO', 'cerrada', 11),
(34, 4, 4, '763.43', '2026-12-25', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(35, 4, 5, '763.43', '2027-01-25', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(36, 4, 6, '763.43', '2027-02-25', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(37, 4, 7, '763.43', '2027-03-25', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(38, 4, 8, '763.43', '2027-04-25', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(39, 4, 9, '763.43', '2027-05-25', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(40, 4, 10, '763.43', '2027-06-25', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(41, 4, 11, '763.43', '2027-07-25', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(42, 4, 12, '763.43', '2027-08-25', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(43, 4, 13, '763.43', '2027-09-25', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(44, 4, 14, '763.43', '2027-10-25', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(45, 4, 15, '763.43', '2027-11-25', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(46, 4, 16, '763.43', '2027-12-25', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(47, 4, 17, '763.43', '2028-01-25', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(48, 4, 18, '763.43', '2028-02-25', '0.00', NULL, 'PENDIENTE', 'abierta', NULL),
(49, 5, 1, '2082.08', '2026-09-25', '2082.08', '2026-08-25 13:07:28', 'PAGADO', 'cerrada', 6),
(50, 5, 2, '2082.08', '2026-10-25', '2082.08', '2026-08-25 13:07:28', 'PAGADO', 'cerrada', 6),
(51, 5, 3, '2082.08', '2026-11-25', '2082.08', '2026-08-25 13:07:28', 'PAGADO', 'cerrada', 6),
(52, 5, 4, '2082.08', '2026-12-25', '2082.08', '2026-08-25 13:07:28', 'PAGADO', 'cerrada', 6),
(53, 5, 5, '2082.08', '2027-01-25', '2082.08', '2026-08-25 13:07:28', 'PAGADO', 'cerrada', 6),
(54, 5, 6, '2082.08', '2027-02-25', '2082.08', '2026-08-25 13:07:28', 'PAGADO', 'cerrada', 6),
(55, 5, 7, '2082.08', '2027-03-25', '2082.08', '2026-08-25 13:07:28', 'PAGADO', 'cerrada', 6),
(56, 5, 8, '2082.08', '2027-04-25', '2082.08', '2026-08-25 13:07:28', 'PAGADO', 'cerrada', 6),
(57, 5, 9, '2082.08', '2027-05-25', '2082.08', '2026-08-25 13:07:28', 'PAGADO', 'cerrada', 6),
(58, 5, 10, '2082.08', '2027-06-25', '2082.08', '2026-08-25 13:07:28', 'PAGADO', 'cerrada', 6),
(59, 5, 11, '2082.08', '2027-07-25', '2082.08', '2026-08-25 13:07:28', 'PAGADO', 'cerrada', 6),
(60, 5, 12, '2082.08', '2027-08-25', '2082.08', '2026-08-25 13:07:28', 'PAGADO', 'cerrada', 6);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_devoluciones`
--

CREATE TABLE `detalle_devoluciones` (
  `id` int(11) NOT NULL,
  `devolucion_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `detalle_devoluciones`
--

INSERT INTO `detalle_devoluciones` (`id`, `devolucion_id`, `producto_id`, `cantidad`, `precio_unitario`) VALUES
(1, 1, 2, 1, '350.00'),
(2, 2, 4, 2, '4899.00'),
(3, 3, 3, 2, '1500.00'),
(4, 4, 2, 1, '350.00'),
(5, 5, 6, 1, '5000.00'),
(6, 6, 9, 1, '7000.00'),
(7, 7, 5, 1, '500.00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_entrada_inventario`
--

CREATE TABLE `detalle_entrada_inventario` (
  `id` int(11) NOT NULL,
  `entrada_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_compra` decimal(10,2) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_entrada_inventario`
--

INSERT INTO `detalle_entrada_inventario` (`id`, `entrada_id`, `producto_id`, `cantidad`, `precio_compra`, `precio_venta`) VALUES
(1, 1, 1, 6, '10000.00', '12500.00'),
(2, 1, 3, 4, '1200.00', '1500.00'),
(3, 1, 10, 3, '400.00', '800.00'),
(4, 1, 11, 5, '700.00', '1200.00'),
(5, 2, 8, 7, '400.00', '700.00'),
(6, 3, 7, 2, '300.00', '500.00'),
(7, 4, 9, 5, '5000.00', '7000.00'),
(8, 5, 13, 9, '800.00', '1399.00'),
(9, 5, 9, 3, '5000.00', '7000.00'),
(10, 6, 7, 5, '300.00', '500.00'),
(11, 6, 3, 1, '1200.00', '1500.00'),
(12, 6, 11, 3, '700.00', '1200.00'),
(13, 6, 14, 2, '300.00', '696.00'),
(14, 7, 12, 3, '5500.00', '9000.00'),
(15, 7, 15, 7, '6000.00', '9994.00'),
(16, 8, 5, 6, '340.00', '500.00'),
(17, 8, 16, 6, '800.00', '999.00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_ventas`
--

CREATE TABLE `detalle_ventas` (
  `id` int(11) NOT NULL,
  `venta_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `descuento_unitario` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_ventas`
--

INSERT INTO `detalle_ventas` (`id`, `venta_id`, `producto_id`, `cantidad`, `precio_unitario`, `subtotal`, `descuento_unitario`) VALUES
(2, 2, 3, 1, '1500.00', '1500.00', '0.00'),
(3, 3, 3, 2, '1500.00', '3000.00', '0.00'),
(4, 4, 3, 3, '1500.00', '4500.00', '0.00'),
(5, 5, 3, 1, '1500.00', '1500.00', '0.00'),
(6, 6, 3, 2, '1500.00', '3000.00', '0.00'),
(7, 7, 3, 1, '1500.00', '1500.00', '0.00'),
(8, 8, 3, 3, '1500.00', '4500.00', '0.00'),
(9, 9, 3, 1, '1500.00', '1500.00', '0.00'),
(10, 10, 4, 2, '4899.00', '9798.00', '0.00'),
(11, 11, 2, 1, '350.00', '350.00', '0.00'),
(12, 11, 4, 1, '4899.00', '4899.00', '0.00'),
(13, 12, 11, 1, '1200.00', '1200.00', '0.00'),
(14, 13, 2, 1, '350.00', '350.00', '0.00'),
(15, 13, 11, 1, '1200.00', '1200.00', '0.00'),
(16, 14, 2, 1, '350.00', '350.00', '0.00'),
(17, 14, 5, 1, '500.00', '500.00', '0.00'),
(18, 14, 9, 1, '7000.00', '7000.00', '0.00'),
(19, 15, 3, 1, '1500.00', '1500.00', '0.00'),
(20, 15, 2, 1, '350.00', '350.00', '0.00'),
(21, 16, 2, 1, '350.00', '350.00', '0.00'),
(22, 17, 2, 1, '350.00', '350.00', '0.00'),
(23, 18, 2, 1, '350.00', '350.00', '0.00'),
(24, 19, 2, 1, '350.00', '332.50', '17.50'),
(25, 20, 2, 1, '350.00', '350.00', '0.00'),
(26, 21, 5, 1, '500.00', '500.00', '0.00'),
(27, 22, 2, 1, '350.00', '320.00', '30.00'),
(28, 23, 9, 1, '7000.00', '7000.00', '0.00'),
(29, 23, 3, 4, '1500.00', '5400.00', '150.00'),
(30, 24, 3, 1, '1500.00', '1500.00', '0.00'),
(31, 25, 3, 1, '1500.00', '1500.00', '0.00'),
(32, 26, 5, 1, '500.00', '500.00', '0.00'),
(33, 27, 5, 1, '500.00', '500.00', '0.00'),
(34, 28, 2, 1, '350.00', '350.00', '0.00'),
(35, 29, 2, 1, '350.00', '350.00', '0.00'),
(36, 36, 3, 10, '1500.00', '15000.00', '0.00'),
(37, 37, 7, 1, '500.00', '475.00', '25.00'),
(38, 37, 2, 1, '350.00', '315.00', '35.00'),
(39, 38, 2, 1, '350.00', '350.00', '0.00'),
(40, 39, 2, 1, '350.00', '350.00', '0.00'),
(41, 40, 2, 1, '350.00', '350.00', '0.00'),
(42, 41, 2, 1, '350.00', '350.00', '0.00'),
(43, 42, 2, 1, '350.00', '350.00', '0.00'),
(44, 43, 2, 1, '350.00', '350.00', '0.00'),
(45, 44, 2, 1, '350.00', '350.00', '0.00'),
(46, 45, 2, 1, '350.00', '350.00', '0.00'),
(47, 46, 2, 1, '350.00', '350.00', '0.00'),
(49, 47, 2, 1, '350.00', '350.00', '0.00'),
(50, 48, 2, 1, '350.00', '350.00', '0.00'),
(52, 49, 2, 1, '350.00', '350.00', '0.00'),
(53, 50, 13, 2, '1399.00', '2798.00', '0.00'),
(55, 51, 13, 2, '1399.00', '2798.00', '0.00'),
(56, 53, 2, 1, '350.00', '332.50', '17.50'),
(57, 53, 5, 1, '500.00', '400.00', '100.00'),
(58, 54, 2, 1, '350.00', '350.00', '0.00'),
(60, 55, 6, 1, '5000.00', '5000.00', '0.00'),
(61, 57, 2, 1, '350.00', '350.00', '0.00'),
(70, 59, 7, 1, '500.00', '435.00', '65.00'),
(71, 58, 9, 1, '7000.00', '6300.00', '700.00'),
(72, 58, 2, 1, '350.00', '350.00', '0.00'),
(73, 60, 9, 1, '7000.00', '7000.00', '0.00'),
(74, 61, 5, 1, '500.00', '500.00', '0.00'),
(75, 62, 5, 1, '500.00', '500.00', '0.00'),
(76, 63, 2, 1, '350.00', '350.00', '0.00'),
(77, 64, 2, 1, '350.00', '350.00', '0.00'),
(78, 65, 2, 1, '350.00', '350.00', '0.00'),
(79, 66, 8, 1, '700.00', '700.00', '0.00'),
(80, 67, 2, 1, '350.00', '350.00', '0.00'),
(81, 68, 9, 1, '7000.00', '7000.00', '0.00'),
(83, 69, 7, 1, '500.00', '500.00', '0.00'),
(85, 70, 9, 1, '7000.00', '7000.00', '0.00'),
(87, 72, 3, 1, '1500.00', '1350.00', '150.00'),
(88, 73, 5, 1, '500.00', '500.00', '0.00'),
(89, 75, 5, 1, '500.00', '500.00', '0.00'),
(90, 76, 9, 1, '7000.00', '7000.00', '0.00'),
(91, 77, 9, 1, '7000.00', '7000.00', '0.00'),
(92, 78, 9, 1, '7000.00', '7000.00', '0.00'),
(94, 80, 9, 1, '7000.00', '7000.00', '0.00'),
(95, 81, 9, 1, '7000.00', '7000.00', '0.00'),
(96, 82, 2, 1, '350.00', '350.00', '0.00'),
(97, 82, 3, 1, '1500.00', '1500.00', '0.00'),
(98, 83, 15, 1, '9994.00', '9994.00', '0.00'),
(99, 84, 15, 2, '9994.00', '19988.00', '0.00');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `devoluciones`
--

CREATE TABLE `devoluciones` (
  `id` int(11) NOT NULL,
  `venta_id` int(11) NOT NULL,
  `motivo` text NOT NULL,
  `total_reembolso` decimal(10,2) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha` timestamp NULL DEFAULT current_timestamp(),
  `cliente` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `devoluciones`
--

INSERT INTO `devoluciones` (`id`, `venta_id`, `motivo`, `total_reembolso`, `usuario_id`, `fecha`, `cliente`) VALUES
(1, 20, 'Devolución por: Producto Defectuoso - cliente insatisfecho', '350.00', 10, '2026-08-19 21:46:30', 'Inversiones JA'),
(2, 10, 'Devolución por: Producto Defectuoso - cliente insatisfecho', '9798.00', 10, '2026-08-19 21:49:54', 'Consumidor Final'),
(3, 3, 'Devolución por: Error de Cobro en Caja - mal cambio al cliente', '3000.00', 10, '2026-08-19 22:02:43', 'Consumidor Final'),
(4, 49, 'Devolución por: Error de Cobro en Caja - cliente no desea continuar', '350.00', 6, '2026-08-20 23:28:25', 'María Fernanda López'),
(5, 55, 'Devolución por: Producto Defectuoso - Producto con desperfectos de fabrica', '5000.00', 10, '2026-08-21 19:34:02', 'María Fernanda López'),
(6, 70, 'Devolución por: Producto Defectuoso - cliente se da de baja', '7000.00', 10, '2026-08-23 03:13:39', 'Betany Gisselle Ruiz Reyes'),
(7, 27, 'Devolución por: Producto Defectuoso - Producto salió quebrado, se facturará uno nuevo', '500.00', 10, '2026-08-24 03:33:48', 'Distribuidora del Sur S.A.');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entradas_inventario`
--

CREATE TABLE `entradas_inventario` (
  `id` int(11) NOT NULL,
  `numero_entrada` varchar(20) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `fecha_ingreso` datetime DEFAULT current_timestamp(),
  `total_items` int(11) NOT NULL,
  `total_unidades` int(11) NOT NULL,
  `estado` enum('ACTIVO','ANULADO') NOT NULL DEFAULT 'ACTIVO'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `entradas_inventario`
--

INSERT INTO `entradas_inventario` (`id`, `numero_entrada`, `usuario_id`, `fecha_ingreso`, `total_items`, `total_unidades`, `estado`) VALUES
(1, 'INV-20260818-0001', 6, '2026-08-17 23:10:07', 4, 18, 'ANULADO'),
(2, 'INV-20260818-0002', 6, '2026-08-17 23:10:23', 1, 7, 'ANULADO'),
(3, 'INV-20260818-0003', 6, '2026-08-17 23:44:15', 1, 2, 'ACTIVO'),
(4, 'INV-20260819-0001', 6, '2026-08-18 22:55:23', 1, 5, 'ANULADO'),
(5, 'INV-20260819-0002', 10, '2026-08-19 20:52:04', 2, 12, 'ACTIVO'),
(6, 'INV-20260819-0003', 10, '2026-08-19 20:59:09', 4, 11, 'ACTIVO'),
(7, 'INV-20260821-0001', 10, '2026-08-21 12:31:55', 2, 10, 'ACTIVO'),
(8, 'INV-20260823-0001', 10, '2026-08-23 20:29:33', 2, 12, 'ACTIVO');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_venta`
--

CREATE TABLE `pagos_venta` (
  `id` int(11) NOT NULL,
  `venta_id` int(11) NOT NULL,
  `metodo` varchar(50) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `detalle` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pagos_venta`
--

INSERT INTO `pagos_venta` (`id`, `venta_id`, `metodo`, `monto`, `detalle`) VALUES
(1, 2, 'Efectivo', '2000.00', 'Efectivo'),
(2, 3, 'Efectivo', '4000.00', 'Efectivo'),
(3, 4, 'Efectivo', '6000.00', 'Efectivo'),
(4, 5, 'Efectivo', '3000.00', 'Efectivo'),
(5, 6, 'Efectivo', '3000.00', 'Efectivo'),
(6, 6, 'Tarjeta', '450.00', 'Tarjeta (****9900 - V: 998898)'),
(7, 7, 'Efectivo', '2000.00', 'Efectivo'),
(8, 8, 'Efectivo', '6000.00', 'Efectivo'),
(9, 9, 'Efectivo', '1500.00', 'Efectivo'),
(10, 9, 'Efectivo', '1725.00', 'Efectivo'),
(11, 10, 'Tarjeta', '5000.00', 'Tarjeta (****8787 - V: 9889)'),
(12, 10, 'Efectivo', '7000.00', 'Efectivo'),
(13, 11, 'Efectivo', '6000.00', 'Efectivo'),
(14, 12, 'Efectivo', '2000.00', 'Efectivo'),
(15, 13, 'Efectivo', '1500.00', 'Efectivo'),
(16, 14, 'Efectivo', '8000.00', 'Efectivo'),
(17, 15, 'Efectivo', '2000.00', 'Efectivo');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `pagos_ventas`
--

CREATE TABLE `pagos_ventas` (
  `id` int(11) NOT NULL,
  `venta_id` int(11) NOT NULL,
  `metodo` varchar(50) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `detalle` varchar(255) DEFAULT NULL,
  `titular` varchar(150) DEFAULT NULL,
  `digitos` varchar(10) DEFAULT NULL,
  `voucher` varchar(50) DEFAULT NULL,
  `fecha_registro` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `pagos_ventas`
--

INSERT INTO `pagos_ventas` (`id`, `venta_id`, `metodo`, `monto`, `detalle`, `titular`, `digitos`, `voucher`, `fecha_registro`) VALUES
(1, 16, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-18 00:23:48'),
(2, 17, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-18 00:26:36'),
(3, 18, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-18 00:28:25'),
(4, 19, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-18 01:13:59'),
(5, 20, 'Tarjeta', '350.00', 'Tarjeta (****9989 - V: 98998)', 'Carlos', '9989', '98998', '2026-08-18 01:15:09'),
(6, 21, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-18 01:23:23'),
(7, 22, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-18 08:28:43'),
(8, 23, 'Tarjeta', '12400.00', 'Tarjeta (****2232 - V: 098882)', 'Carlos aranda', '2232', '098882', '2026-08-18 21:11:26'),
(9, 24, 'Efectivo', '2000.00', 'Efectivo', NULL, NULL, NULL, '2026-08-18 21:28:51'),
(10, 25, 'Efectivo', '1500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-18 22:54:14'),
(11, 26, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-19 13:02:28'),
(12, 27, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-19 13:03:04'),
(13, 28, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-19 13:13:49'),
(14, 29, 'Tarjeta', '350.00', 'Tarjeta (****0099 - V: 909090)', 'Carlos', '0099', '909090', '2026-08-19 13:34:36'),
(15, 36, 'Efectivo', '20000.00', 'Efectivo', NULL, NULL, NULL, '2026-08-19 15:03:57'),
(16, 37, 'Efectivo', '200.00', 'Efectivo', NULL, NULL, NULL, '2026-08-19 20:35:59'),
(17, 37, 'Tarjeta', '590.00', 'Tarjeta (****9900 - V: 090909)', 'Armando Castro', '9900', '090909', '2026-08-19 20:35:59'),
(18, 38, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-20 13:46:23'),
(19, 39, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-20 14:01:09'),
(20, 40, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-20 14:05:51'),
(21, 42, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-20 15:07:49'),
(22, 43, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-20 15:23:37'),
(23, 44, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-20 15:24:02'),
(24, 46, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-20 15:33:19'),
(25, 47, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-20 15:47:42'),
(26, 49, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-20 15:57:17'),
(27, 51, 'Tarjeta', '2798.00', 'Tarjeta (****8899 - V: 988798)', 'administrador', '8899', '988798', '2026-08-20 16:24:06'),
(28, 53, 'Efectivo', '400.00', 'Efectivo', NULL, NULL, NULL, '2026-08-21 12:26:58'),
(29, 53, 'Tarjeta', '332.50', 'Tarjeta (****6655 - V: 990099)', 'Carlos', '6655', '990099', '2026-08-21 12:26:58'),
(30, 55, 'Efectivo', '5000.00', 'Efectivo', NULL, NULL, NULL, '2026-08-21 12:30:38'),
(31, 57, 'Efectivo', '100.00', 'Efectivo', NULL, NULL, NULL, '2026-08-22 12:39:08'),
(32, 57, 'Tarjeta', '250.00', 'Tarjeta (****9988 - V: 989788)', 'Carlos', '9988', '989788', '2026-08-22 12:39:08'),
(33, 59, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-22 12:50:59'),
(34, 58, 'Efectivo', '7000.00', 'Efectivo', NULL, NULL, NULL, '2026-08-22 13:18:31'),
(35, 60, 'Efectivo', '4000.00', 'Efectivo', NULL, NULL, NULL, '2026-08-22 13:33:40'),
(36, 60, 'Tarjeta', '3000.00', 'Tarjeta (****5566 - V: 56456)', 'Carlos', '5566', '56456', '2026-08-22 13:33:40'),
(37, 61, 'Tarjeta', '500.00', 'Tarjeta (****8899 - V: 98987)', 'Carlos', '8899', '98987', '2026-08-22 13:48:52'),
(38, 62, 'Tarjeta', '250.00', 'Tarjeta (****9988 - V: 9789)', 'carlos', '9988', '9789', '2026-08-22 13:49:39'),
(39, 62, 'Efectivo', '300.00', 'Efectivo', NULL, NULL, NULL, '2026-08-22 13:49:39'),
(40, 63, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-22 14:08:17'),
(41, 64, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-22 14:10:21'),
(42, 65, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-22 14:18:29'),
(43, 66, 'Efectivo', '800.00', 'Efectivo', NULL, NULL, NULL, '2026-08-22 14:25:41'),
(44, 67, 'Efectivo', '400.00', 'Efectivo', NULL, NULL, NULL, '2026-08-22 14:46:37'),
(45, 68, 'Efectivo', '8000.00', 'Efectivo', NULL, NULL, NULL, '2026-08-22 14:51:48'),
(47, 69, 'Efectivo', '600.00', 'Efectivo', NULL, NULL, NULL, '2026-08-22 14:53:57'),
(48, 70, 'Efectivo', '5000.00', 'Efectivo', NULL, NULL, NULL, '2026-08-22 20:10:01'),
(49, 70, 'Tarjeta', '2000.00', 'Tarjeta (****4589 - V: 925788)', 'Betany Ruiz', '4589', '925788', '2026-08-22 20:10:01'),
(50, 72, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-23 20:28:13'),
(51, 72, 'Tarjeta', '850.00', 'Tarjeta (****8899 - V: 989878)', 'Maria', '8899', '989878', '2026-08-23 20:28:13'),
(52, 73, 'Tarjeta', '500.00', 'Tarjeta (****9988 - V: 98989)', 'Armando', '9988', '98989', '2026-08-23 20:31:30'),
(53, 75, 'Efectivo', '500.00', 'Efectivo', NULL, NULL, NULL, '2026-08-23 20:34:41'),
(54, 76, 'Efectivo', '1000.00', 'Prima de Crédito Inicial', NULL, NULL, NULL, '2026-08-24 13:13:36'),
(55, 77, 'Crédito', '6000.00', 'Contrato Crédito (12 meses - Cuota: L. 625.00)', NULL, NULL, NULL, '2026-08-24 13:23:43'),
(56, 77, 'Efectivo', '1000.00', 'Efectivo', NULL, NULL, NULL, '2026-08-24 13:23:43'),
(57, 78, 'Crédito', '6000.00', 'Contrato Crédito (12 meses - Cuota: L. 625.00)', NULL, NULL, NULL, '2026-08-24 13:45:13'),
(58, 78, 'Efectivo', '1000.00', 'Efectivo', NULL, NULL, NULL, '2026-08-24 13:45:13'),
(61, 80, 'Crédito', '5000.00', 'Contrato Crédito (6 meses - Cuota: L. 937.50)', NULL, NULL, NULL, '2026-08-24 14:00:32'),
(62, 80, 'Efectivo', '2000.00', 'Efectivo', NULL, NULL, NULL, '2026-08-24 14:00:32'),
(63, 81, 'Crédito', '7000.00', 'Contrato Crédito (12 meses - Cuota: L. 729.17)', NULL, NULL, NULL, '2026-08-24 20:52:41'),
(64, 82, 'Crédito', '1850.00', 'Contrato Crédito (12 meses - Cuota: L. 192.71)', NULL, NULL, NULL, '2026-08-24 20:57:56'),
(65, 83, 'Crédito', '9994.00', 'Contrato Crédito (18 meses - Cuota: L. 763.43)', NULL, NULL, NULL, '2026-08-24 21:26:48'),
(66, 84, 'Crédito', '19988.00', 'Contrato Crédito (12 meses - Cuota: L. 2082.08)', NULL, NULL, NULL, '2026-08-25 12:55:40');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `codigo_barra` varchar(50) DEFAULT NULL,
  `nombre` varchar(150) NOT NULL,
  `precio_compra` decimal(10,2) NOT NULL,
  `precio_venta` decimal(10,2) NOT NULL,
  `stock` int(11) NOT NULL DEFAULT 0,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `codigo_barra`, `nombre`, `precio_compra`, `precio_venta`, `stock`, `fecha_creacion`) VALUES
(1, '750100000001', 'Laptop HP 15\"', '10000.00', '12500.00', 38, '2026-08-14 04:44:37'),
(2, '750100000002', 'Mouse Inalámbrico', '200.00', '350.00', 1, '2026-08-14 04:44:37'),
(3, '998988999', 'Camisa embarazada', '1200.00', '1500.00', 3, '2026-08-14 04:49:15'),
(4, '09090909123', 'Barra de sonido', '3899.00', '4899.00', 43, '2026-08-18 02:05:47'),
(5, '750100000003', 'Teclado Gaming', '340.00', '500.00', 6, '2026-08-18 05:33:56'),
(6, '750100000004', 'bateria de respaldo', '3400.00', '5000.00', 10, '2026-08-18 05:48:57'),
(7, '750100000005', 'Cable de carga de pc', '300.00', '500.00', 7, '2026-08-18 05:49:55'),
(8, '750100000006', 'cobertor', '400.00', '700.00', 9, '2026-08-18 05:57:36'),
(9, '750100000007', 'silla gaming', '5000.00', '7000.00', 1, '2026-08-18 05:58:14'),
(10, '750100000008', 'reposa manos', '400.00', '800.00', 2, '2026-08-18 05:58:59'),
(11, '750100000009', 'mouse Cassio', '700.00', '1200.00', 22, '2026-08-18 06:10:07'),
(12, '8899898989898', 'Parlante JBL', '5500.00', '9000.00', 6, '2026-08-20 03:37:11'),
(13, '989809809', 'Parlante Xiomi', '800.00', '1399.00', 7, '2026-08-20 03:52:04'),
(14, '750100000011', 'Parlante Awei', '300.00', '696.00', 2, '2026-08-20 03:59:09'),
(15, '8899898989893', 'Parlante JBL extreme', '6000.00', '9994.00', 4, '2026-08-21 19:31:55'),
(16, '750100000015', 'Carro Parlante', '800.00', '999.00', 6, '2026-08-24 03:29:33');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` enum('admin','vendedor') NOT NULL DEFAULT 'vendedor',
  `estado` tinyint(1) DEFAULT 1,
  `fecha_creacion` timestamp NULL DEFAULT current_timestamp(),
  `intentos_fallidos` int(11) DEFAULT 0,
  `token_recuperacion` varchar(255) DEFAULT NULL,
  `token_expiracion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password`, `rol`, `estado`, `fecha_creacion`, `intentos_fallidos`, `token_recuperacion`, `token_expiracion`) VALUES
(1, 'Administrador', 'admin@sistema.com', 'admin123', 'admin', 1, '2026-08-14 03:39:30', 0, NULL, NULL),
(5, 'Joseph Alejandro Mayorga', 'jojo2024@gmail.com', 'admin123', 'vendedor', 1, '2026-08-14 07:11:56', 0, NULL, NULL),
(6, 'Bryan Alejandro Mayorga', 'aleebryanmayorga@gmail.com', '$2y$10$TDOyAzdwNvAJFSyt//vL9eSlARW907.wzi1RcxC5QkQ5BUEfUr35G', 'admin', 1, '2026-08-14 07:36:26', 0, NULL, NULL),
(9, 'Bryan Mayorga', 'aleebryan@gmail.com', '$2y$10$syvv9autUnPjGYoZqzWRX./JndARNR.g.JcVmUCL3xrqhrMufrtlu', 'admin', 1, '2026-08-19 19:28:16', 0, NULL, NULL),
(10, 'Administrador Prueba', 'admin@admin.com', '$2y$10$PhHrMCb/Dy4C/oq/8yNgwe6MbuZYUizki/mnTQvgIwCP932E2SbE2', 'admin', 1, '2026-08-19 19:34:05', 0, NULL, NULL),
(11, 'Vendedor Prueba', 'ventas@admin.com', '$2y$10$1zIMvfoK4d7iophtANEDxucsUqr4M4.blGCytrG5cJErP47YWehv.', 'vendedor', 1, '2026-08-19 19:34:29', 0, NULL, NULL),
(12, 'Betany Gisselle Ruiz Reyes', 'rgisselleruiz98@gmail.com', '$2y$10$ij.Ht0bpFsH8mvt13/poEOotcZjsGa7WNQfb7Ks9da14bHiSvpODm', 'admin', 1, '2026-08-24 02:27:22', 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id_transaccion` int(11) NOT NULL,
  `numero_factura` varchar(50) DEFAULT NULL,
  `cliente_identidad` varchar(15) NOT NULL,
  `cliente_codigo_bp` varchar(20) DEFAULT 'BP000',
  `usuario_id` int(11) NOT NULL,
  `total` decimal(10,2) NOT NULL,
  `fecha_venta` timestamp NULL DEFAULT current_timestamp(),
  `estado_caja` varchar(20) DEFAULT 'abierta',
  `cliente_rtn` varchar(20) DEFAULT '0000000000000',
  `ahorro_total` decimal(10,2) DEFAULT 0.00,
  `monto_abonado` decimal(10,2) DEFAULT 0.00,
  `monto_recibido` decimal(10,2) DEFAULT 0.00,
  `cambio_entregado` decimal(10,2) DEFAULT 0.00,
  `cliente_nombre` varchar(150) DEFAULT 'Consumidor Final',
  `tipo_comprobante` varchar(20) DEFAULT 'Factura',
  `metodo_pago` varchar(100) DEFAULT 'Efectivo',
  `monto_efectivo` decimal(10,2) DEFAULT 0.00,
  `monto_tarjeta` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id_transaccion`, `numero_factura`, `cliente_identidad`, `cliente_codigo_bp`, `usuario_id`, `total`, `fecha_venta`, `estado_caja`, `cliente_rtn`, `ahorro_total`, `monto_abonado`, `monto_recibido`, `cambio_entregado`, `cliente_nombre`, `tipo_comprobante`, `metodo_pago`, `monto_efectivo`, `monto_tarjeta`) VALUES
(2, NULL, 'BP007', 'BP000', 1, '1725.00', '2026-08-14 05:59:54', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '0.00', 'Consumidor Final', 'Factura', 'Efectivo', '0.00', '0.00'),
(3, NULL, 'BP005', 'BP000', 1, '3000.00', '2026-08-14 06:12:30', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '0.00', 'Consumidor Final', 'Factura', 'Efectivo', '0.00', '0.00'),
(4, NULL, 'BP006', 'BP000', 1, '4500.00', '2026-08-14 06:15:19', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '0.00', 'Consumidor Final', 'Factura', 'Efectivo', '0.00', '0.00'),
(5, NULL, 'BP005', 'BP000', 1, '1500.00', '2026-08-14 06:21:03', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '0.00', 'Consumidor Final', 'Factura', 'Efectivo', '0.00', '0.00'),
(6, NULL, 'BP007', 'BP000', 1, '3000.00', '2026-08-14 06:58:51', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '0.00', 'Consumidor Final', 'Factura', 'Efectivo', '0.00', '0.00'),
(7, NULL, 'BP007', 'BP000', 5, '1500.00', '2026-08-14 07:21:16', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '0.00', 'Consumidor Final', 'Factura', 'Efectivo', '0.00', '0.00'),
(8, NULL, 'BP008', 'BP000', 5, '4500.00', '2026-08-14 13:16:37', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '0.00', 'Consumidor Final', 'Factura', 'Efectivo', '0.00', '0.00'),
(9, NULL, 'BP007', 'BP000', 6, '1500.00', '2026-08-17 18:30:59', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '0.00', 'Consumidor Final', 'Factura', 'Efectivo', '0.00', '0.00'),
(10, NULL, 'BP010', 'BP000', 6, '9798.00', '2026-08-18 02:07:28', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '0.00', 'Consumidor Final', 'Factura', 'Efectivo', '0.00', '0.00'),
(11, NULL, 'BP006', 'BP000', 6, '5249.00', '2026-08-18 04:38:39', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '0.00', 'Consumidor Final', 'Factura', 'Efectivo', '0.00', '0.00'),
(12, NULL, 'BP005', 'BP000', 6, '1200.00', '2026-08-18 06:48:44', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '0.00', 'Consumidor Final', 'Factura', 'Efectivo', '0.00', '0.00'),
(13, NULL, 'BP008', 'BP000', 6, '1550.00', '2026-08-18 06:58:22', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '0.00', 'Consumidor Final', 'Factura', 'Efectivo', '0.00', '0.00'),
(14, NULL, 'BP007', 'BP000', 6, '7850.00', '2026-08-18 06:59:44', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '0.00', 'Consumidor Final', 'Factura', 'Efectivo', '0.00', '0.00'),
(15, NULL, 'BP005', 'BP000', 6, '1850.00', '2026-08-18 07:01:22', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '0.00', 'Consumidor Final', 'Factura', 'Efectivo', '0.00', '0.00'),
(16, NULL, '', 'BP005', 6, '350.00', '2026-08-18 07:23:48', 'cerrada', '0301198800123', '0.00', '500.00', '500.00', '150.00', 'María Fernanda López', 'Factura', 'Efectivo', '0.00', '0.00'),
(17, NULL, '', 'BP005', 6, '350.00', '2026-08-18 07:26:36', 'cerrada', '0301198800123', '0.00', '500.00', '500.00', '150.00', 'María Fernanda López', 'Factura', 'Efectivo', '0.00', '0.00'),
(18, NULL, '', 'BP005', 6, '350.00', '2026-08-18 07:28:25', 'cerrada', '0301198800123', '0.00', '500.00', '500.00', '150.00', 'María Fernanda López', 'Factura', 'Efectivo', '0.00', '0.00'),
(19, NULL, '', 'BP006', 6, '332.50', '2026-08-18 08:13:59', 'cerrada', '05019015896321', '17.50', '500.00', '500.00', '167.50', 'Distribuidora del Sur S.A.', 'Factura', 'Efectivo', '0.00', '0.00'),
(20, NULL, '', 'BP008', 6, '350.00', '2026-08-18 08:15:09', 'cerrada', '03181999007104', '0.00', '350.00', '0.00', '0.00', 'Inversiones JA', 'Factura', 'Tarjeta', '0.00', '0.00'),
(21, NULL, '', 'BP012', 6, '500.00', '2026-08-18 08:23:23', 'cerrada', '88997867867', '0.00', '500.00', '500.00', '0.00', 'Carlos zamora', 'Factura', 'Efectivo', '0.00', '0.00'),
(22, NULL, '', 'BP013', 6, '320.00', '2026-08-18 15:28:43', 'cerrada', '0318199503494', '30.00', '500.00', '500.00', '180.00', 'Fernanda Rios', 'Factura', 'Efectivo', '0.00', '0.00'),
(23, NULL, '', 'BP006', 6, '12400.00', '2026-08-19 04:11:26', 'cerrada', '05019015896321', '600.00', '12400.00', '0.00', '0.00', 'Distribuidora del Sur S.A.', 'Factura', 'Tarjeta', '0.00', '0.00'),
(24, NULL, '', 'BP006', 6, '1500.00', '2026-08-19 04:28:51', 'cerrada', '05019015896321', '0.00', '2000.00', '2000.00', '500.00', 'Distribuidora del Sur S.A.', 'Ticket', 'Efectivo', '0.00', '0.00'),
(25, NULL, '', 'BP013', 6, '1500.00', '2026-08-19 05:54:14', 'cerrada', '0501-1988-000009', '0.00', '1500.00', '1500.00', '0.00', 'Carlos Eduardo espina', 'Factura', 'Efectivo', '0.00', '0.00'),
(26, NULL, '', 'BP007', 10, '500.00', '2026-08-19 20:02:28', 'cerrada', '1201200000456', '0.00', '500.00', '500.00', '0.00', 'Roberto Gómez', 'Factura', 'Efectivo', '0.00', '0.00'),
(27, NULL, '', 'BP006', 10, '500.00', '2026-08-19 20:03:04', 'cerrada', '05019015896321', '0.00', '500.00', '500.00', '0.00', 'Distribuidora del Sur S.A.', 'Factura', 'Efectivo', '0.00', '0.00'),
(28, NULL, '', 'BP005', 10, '350.00', '2026-08-19 20:13:49', 'cerrada', '0301198800123', '0.00', '500.00', '500.00', '150.00', 'María Fernanda López', 'Factura', 'Efectivo', '0.00', '0.00'),
(29, NULL, '', 'BP006', 10, '350.00', '2026-08-19 20:34:36', 'cerrada', '05019015896321', '0.00', '350.00', '0.00', '0.00', 'Distribuidora del Sur S.A.', 'Factura', 'Tarjeta', '0.00', '0.00'),
(30, NULL, '', 'BP006', 10, '-350.00', '2026-08-19 21:26:35', 'cerrada', '05019015896321', '0.00', '350.00', '0.00', '0.00', 'Distribuidora del Sur S.A.', 'Devolución', 'Devolución por: Producto Defectuoso - cliente insatisfecho', '0.00', '0.00'),
(31, NULL, '', 'BP005', 10, '-350.00', '2026-08-19 21:30:54', 'cerrada', '0301198800123', '0.00', '500.00', '500.00', '0.00', 'María Fernanda López', 'Devolución', 'Devolución por: Producto Defectuoso - cliente no esta satisfecho', '0.00', '0.00'),
(32, NULL, '', 'BP005', 10, '-350.00', '2026-08-19 21:34:41', 'cerrada', '0301198800123', '0.00', '500.00', '500.00', '0.00', 'María Fernanda López', 'Devolución', 'Devolución por: Producto Defectuoso - cliente', '0.00', '0.00'),
(33, NULL, '', 'BP008', 10, '-350.00', '2026-08-19 21:46:30', 'cerrada', '03181999007104', '0.00', '350.00', '0.00', '350.00', 'Inversiones JA', 'Devolución', 'Devolución por: Producto Defectuoso - cliente insatisfecho', '0.00', '0.00'),
(34, NULL, 'BP010', 'BP000', 10, '-9798.00', '2026-08-19 21:49:54', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '9798.00', 'Consumidor Final', 'Devolución', 'Devolución por: Producto Defectuoso - cliente insatisfecho', '0.00', '0.00'),
(35, NULL, 'BP005', 'BP000', 10, '-3000.00', '2026-08-19 22:02:43', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '3000.00', 'Consumidor Final', 'Devolución', 'Devolución por: Error de Cobro en Caja - mal cambio al cliente', '0.00', '0.00'),
(36, NULL, '', 'BP008', 10, '15000.00', '2026-08-19 22:03:57', 'cerrada', '03181999007104', '0.00', '20000.00', '20000.00', '5000.00', 'Inversiones JA', 'Factura', 'Efectivo', '0.00', '0.00'),
(37, NULL, '', 'BP005', 10, '790.00', '2026-08-20 03:35:59', 'cerrada', '0301198800123', '60.00', '790.00', '200.00', '0.00', 'María Fernanda López', 'Factura', 'Efectivo / Tarjeta', '0.00', '0.00'),
(38, '00000001', '', 'BP000', 6, '350.00', '2026-08-20 20:46:23', 'cerrada', '0000000000000', '0.00', '500.00', '500.00', '150.00', 'Consumidor Final', 'Factura', 'Efectivo', '0.00', '0.00'),
(39, '00000002', '', 'BP000', 6, '350.00', '2026-08-20 21:01:09', 'cerrada', '0000000000000', '0.00', '500.00', '500.00', '150.00', 'Consumidor Final', 'Factura', 'Efectivo', '0.00', '0.00'),
(40, '000-001-01-00000003', '', 'BP005', 6, '350.00', '2026-08-20 21:05:51', 'cerrada', '0301198800123', '0.00', '500.00', '500.00', '150.00', 'María Fernanda López', 'Factura', 'Efectivo', '0.00', '0.00'),
(41, NULL, '', 'BP005', 6, '350.00', '2026-08-20 21:25:07', 'cerrada', '0301198800123', '0.00', '0.00', '0.00', '0.00', 'María Fernanda López', 'Orden Pendiente', 'Pendiente', '0.00', '0.00'),
(42, '000-001-01-00000004', '', 'BP005', 6, '350.00', '2026-08-20 22:07:49', 'cerrada', '0301198800123', '0.00', '500.00', '500.00', '150.00', 'María Fernanda López', 'Factura', 'Efectivo', '0.00', '0.00'),
(43, NULL, '', 'BP007', 6, '350.00', '2026-08-20 22:23:37', 'cerrada', '1201200000456', '0.00', '500.00', '500.00', '150.00', 'Roberto Gómez', 'Orden Pendiente', 'Efectivo', '0.00', '0.00'),
(44, '000-001-01-00000005', '', 'BP007', 6, '350.00', '2026-08-20 22:24:02', 'cerrada', '1201200000456', '0.00', '500.00', '500.00', '150.00', 'Roberto Gómez', 'Factura', 'Efectivo', '0.00', '0.00'),
(45, NULL, '', 'BP006', 6, '350.00', '2026-08-20 22:32:57', 'cerrada', '05019015896321', '0.00', '0.00', '0.00', '0.00', 'Distribuidora del Sur S.A.', 'Orden Pendiente', 'Pendiente', '0.00', '0.00'),
(46, '000-001-01-00000006', '', 'BP006', 6, '350.00', '2026-08-20 22:33:19', 'cerrada', '05019015896321', '0.00', '500.00', '500.00', '150.00', 'Distribuidora del Sur S.A.', 'Factura', 'Efectivo', '0.00', '0.00'),
(47, '000-001-01-00000008', '', 'BP009', 6, '350.00', '2026-08-20 22:47:42', 'cerrada', '031888889129381', '0.00', '500.00', '500.00', '150.00', 'casa de emepelo', 'Factura', 'Efectivo', '0.00', '0.00'),
(48, NULL, '', 'BP005', 6, '350.00', '2026-08-20 22:50:03', 'cerrada', '0301198800123', '0.00', '0.00', '0.00', '0.00', 'María Fernanda López', 'Orden Pendiente', 'Pendiente', '0.00', '0.00'),
(49, '000-001-01-00000009', '', 'BP005', 6, '350.00', '2026-08-20 22:57:17', 'cerrada', '0301198800123', '0.00', '500.00', '500.00', '150.00', 'María Fernanda López', 'Factura', 'Efectivo', '0.00', '0.00'),
(50, NULL, '', 'BP000', 6, '2798.00', '2026-08-20 23:06:27', 'cerrada', '0000000000000', '0.00', '0.00', '0.00', '0.00', 'Consumidor Final', 'Orden Pendiente', 'Pendiente', '0.00', '0.00'),
(51, '000-001-01-00000010', '', 'BP005', 6, '2798.00', '2026-08-20 23:24:06', 'cerrada', '0301198800123', '0.00', '2798.00', '0.00', '0.00', 'María Fernanda López', 'Factura', 'Tarjeta', '0.00', '0.00'),
(52, NULL, '', 'BP005', 6, '-350.00', '2026-08-20 23:28:25', 'cerrada', '0301198800123', '0.00', '500.00', '-500.00', '350.00', 'María Fernanda López', 'Devolución', 'Devolución por: Error de Cobro en Caja - cliente no desea continuar', '0.00', '0.00'),
(53, '000-001-01-00000011', '', 'BP007', 10, '732.50', '2026-08-21 19:26:58', 'cerrada', '1201200000456', '117.50', '732.50', '400.00', '0.00', 'Roberto Gómez', 'Factura', 'Efectivo / Tarjeta', '0.00', '0.00'),
(54, NULL, '', 'BP005', 10, '350.00', '2026-08-21 19:29:41', 'cerrada', '0301198800123', '0.00', '0.00', '0.00', '0.00', 'María Fernanda López', 'Orden Pendiente', 'Pendiente', '0.00', '0.00'),
(55, '000-001-01-00000012', '', 'BP005', 10, '5000.00', '2026-08-21 19:30:38', 'cerrada', '0301198800123', '0.00', '5000.00', '5000.00', '0.00', 'María Fernanda López', 'Factura', 'Efectivo', '0.00', '0.00'),
(56, NULL, '', 'BP005', 10, '-5000.00', '2026-08-21 19:34:02', 'cerrada', '0301198800123', '0.00', '5000.00', '-5000.00', '5000.00', 'María Fernanda López', 'Devolución', 'Devolución por: Producto Defectuoso - Producto con desperfectos de fabrica', '0.00', '0.00'),
(57, '000-001-01-00000013', '', 'BP009', 11, '350.00', '2026-08-22 19:39:08', 'cerrada', '031888889129381', '0.00', '350.00', '100.00', '0.00', 'casa de emepelo', 'Factura', 'Efectivo / Tarjeta', '0.00', '0.00'),
(58, '000-001-01-00000015', '', 'BP008', 11, '6650.00', '2026-08-22 20:18:31', 'cerrada', '03181999007104', '700.00', '7000.00', '7000.00', '350.00', 'Inversiones JA', 'Factura', 'Efectivo', '0.00', '0.00'),
(59, '000-001-01-00000014', '', 'BP006', 11, '435.00', '2026-08-22 19:50:58', 'cerrada', '05019015896321', '65.00', '500.00', '500.00', '65.00', 'Distribuidora del Sur S.A.', 'Factura', 'Efectivo', '0.00', '0.00'),
(60, '000-001-01-00000016', '', 'BP005', 10, '7000.00', '2026-08-22 20:33:40', 'cerrada', '0301198800123', '0.00', '7000.00', '4000.00', '0.00', 'María Fernanda López', 'Factura', 'Efectivo / Tarjeta', '4000.00', '3000.00'),
(61, '000-001-01-00000017', '', 'BP005', 10, '500.00', '2026-08-22 20:48:52', 'cerrada', '0301198800123', '0.00', '500.00', '0.00', '0.00', 'María Fernanda López', 'Factura', 'Tarjeta', '0.00', '500.00'),
(62, '000-001-01-00000018', '', 'BP007', 10, '500.00', '2026-08-22 20:49:39', 'cerrada', '1201200000456', '0.00', '550.00', '300.00', '50.00', 'Roberto Gómez', 'Factura', 'Tarjeta / Efectivo', '300.00', '250.00'),
(63, '000-001-01-00000019', '', 'BP005', 10, '350.00', '2026-08-22 21:08:17', 'cerrada', '0301198800123', '0.00', '500.00', NULL, '150.00', 'María Fernanda López', 'Factura', 'Efectivo', '350.00', '0.00'),
(64, '000-001-01-00000020', '', 'BP005', 10, '350.00', '2026-08-22 21:10:21', 'cerrada', '0301198800123', '0.00', '500.00', '500.00', '150.00', 'María Fernanda López', 'Factura', 'Efectivo', '500.00', '0.00'),
(65, '000-001-01-00000021', '', 'BP006', 10, '350.00', '2026-08-22 21:18:29', 'cerrada', '05019015896321', '0.00', '500.00', '500.00', '150.00', 'Distribuidora del Sur S.A.', 'Factura', 'Efectivo', '500.00', '0.00'),
(66, '000-001-01-00000022', '0000000000000', 'BP000', 10, '700.00', '2026-08-22 21:25:41', 'cerrada', '0000000000000', '0.00', '800.00', '800.00', '100.00', 'Consumidor Final', 'Factura', 'Efectivo', '800.00', '0.00'),
(67, '000-001-01-00000023', '0301198800123', 'BP005', 10, '350.00', '2026-08-22 21:46:37', 'cerrada', '0301198800123', '0.00', '400.00', '400.00', '50.00', 'María Fernanda López', 'Factura', 'Efectivo', '350.00', '0.00'),
(68, '000-001-01-00000024', '05019015896321', 'BP006', 10, '7000.00', '2026-08-22 21:51:48', 'cerrada', '05019015896321', '0.00', '8000.00', '8000.00', '1000.00', 'Distribuidora del Sur S.A.', 'Factura', 'Efectivo', '7000.00', '0.00'),
(69, '000-001-01-00000025', '0000000000000', 'BP000', 11, '500.00', '2026-08-22 21:53:57', 'cerrada', '0000000000000', '0.00', '600.00', '600.00', '100.00', 'Consumidor Final', 'Factura', 'Efectivo', '500.00', '0.00'),
(70, '000-001-01-00000026', '0318199900806', 'BP014', 10, '7000.00', '2026-08-23 03:10:01', 'cerrada', '0318199900806', '0.00', '7000.00', '5000.00', '0.00', 'Betany Gisselle Ruiz Reyes', 'Factura', 'Efectivo / Tarjeta', '5000.00', '2000.00'),
(71, NULL, '0318199900806', 'BP014', 10, '-7000.00', '2026-08-23 03:13:39', 'cerrada', '0318199900806', '0.00', '7000.00', '-5000.00', '7000.00', 'Betany Gisselle Ruiz Reyes', 'Devolución', 'Devolución por: Producto Defectuoso - cliente se da de baja', '0.00', '0.00'),
(72, '000-001-01-00000027', '0301198800123', 'BP005', 10, '1350.00', '2026-08-24 03:28:13', 'cerrada', '0301198800123', '150.00', '1350.00', '500.00', '0.00', 'María Fernanda López', 'Factura', 'Efectivo / Tarjeta', '500.00', '850.00'),
(73, '000-001-01-00000028', '0318999900657', 'BP015', 10, '500.00', '2026-08-24 03:31:30', 'cerrada', '0318999900657', '0.00', '500.00', '0.00', '0.00', 'Almacenes Nuevos', 'Factura', 'Tarjeta', '0.00', '500.00'),
(74, NULL, '', 'BP006', 10, '-500.00', '2026-08-24 03:33:48', 'cerrada', '05019015896321', '0.00', '500.00', '-500.00', '500.00', 'Distribuidora del Sur S.A.', 'Devolución', 'Devolución por: Producto Defectuoso - Producto salió quebrado, se facturará uno nuevo', '0.00', '0.00'),
(75, '000-001-01-00000029', '05019015896321', 'BP006', 10, '500.00', '2026-08-24 03:34:41', 'cerrada', '05019015896321', '0.00', '500.00', '500.00', '0.00', 'Distribuidora del Sur S.A.', 'Factura', 'Efectivo', '500.00', '0.00'),
(76, NULL, '0301198800123', 'BP005', 6, '7000.00', '2026-08-24 20:13:36', 'cerrada', '0301198800123', '0.00', '1000.00', '1000.00', '0.00', 'María Fernanda López', 'Orden Pendiente', 'Efectivo', '1000.00', '0.00'),
(77, '000-001-01-00000030', '0301198800123', 'BP005', 6, '7000.00', '2026-08-24 20:23:43', 'cerrada', '0301198800123', '0.00', '7000.00', '1000.00', '0.00', 'María Fernanda López', 'Factura', 'Crédito / Efectivo', '1000.00', '0.00'),
(78, '000-001-01-00000031', '0301198800123', 'BP005', 6, '7000.00', '2026-08-24 20:45:13', 'cerrada', '0301198800123', '0.00', '7000.00', '1000.00', '0.00', 'María Fernanda López', 'Factura', 'Crédito / Efectivo', '1000.00', '0.00'),
(80, '000-001-01-00000033', '0301198800123', 'BP005', 6, '7000.00', '2026-08-24 21:00:32', 'cerrada', '0301198800123', '0.00', '7000.00', '2000.00', '0.00', 'María Fernanda López', 'Factura', 'Crédito / Efectivo', '2000.00', '0.00'),
(81, '000-001-01-00000034', '0301198800123', 'BP005', 6, '7000.00', '2026-08-25 03:52:41', 'cerrada', '0301198800123', '0.00', '7000.00', '0.00', '0.00', 'María Fernanda López', 'Factura', 'Crédito', '0.00', '0.00'),
(82, '000-001-01-00000035', '0301198800123', 'BP005', 6, '1850.00', '2026-08-25 03:57:56', 'cerrada', '0301198800123', '0.00', '1850.00', '0.00', '0.00', 'María Fernanda López', 'Factura', 'Crédito', '0.00', '0.00'),
(83, '000-001-01-00000036', '0318199900806', 'BP014', 11, '9994.00', '2026-08-25 04:26:48', 'cerrada', '0318199900806', '0.00', '9994.00', '0.00', '0.00', 'Betany Gisselle Ruiz Reyes', 'Factura', 'Crédito', '0.00', '0.00'),
(84, '000-001-01-00000037', '05019015896321', 'BP006', 6, '19988.00', '2026-08-25 19:55:40', 'cerrada', '05019015896321', '0.00', '19988.00', '0.00', '0.00', 'Distribuidora del Sur S.A.', 'Factura', 'Crédito', '0.00', '0.00');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `arqueo_caja`
--
ALTER TABLE `arqueo_caja`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `cierres_caja`
--
ALTER TABLE `cierres_caja`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`rtn_dni`);

--
-- Indices de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `contratos`
--
ALTER TABLE `contratos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `codigo_bp` (`codigo_bp`);

--
-- Indices de la tabla `cuotas_contrato`
--
ALTER TABLE `cuotas_contrato`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contrato_id` (`contrato_id`),
  ADD KEY `fk_cuotas_usuario` (`usuario_id`);

--
-- Indices de la tabla `detalle_devoluciones`
--
ALTER TABLE `detalle_devoluciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_detalle_dev` (`devolucion_id`),
  ADD KEY `fk_detalle_prod` (`producto_id`);

--
-- Indices de la tabla `detalle_entrada_inventario`
--
ALTER TABLE `detalle_entrada_inventario`
  ADD PRIMARY KEY (`id`),
  ADD KEY `entrada_id` (`entrada_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venta_id` (`venta_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `devoluciones`
--
ALTER TABLE `devoluciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_devolucion_venta` (`venta_id`);

--
-- Indices de la tabla `entradas_inventario`
--
ALTER TABLE `entradas_inventario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `numero_entrada` (`numero_entrada`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `pagos_venta`
--
ALTER TABLE `pagos_venta`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `pagos_ventas`
--
ALTER TABLE `pagos_ventas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_venta_id` (`venta_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `codigo_barra` (`codigo_barra`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id_transaccion`),
  ADD KEY `cliente_identidad` (`cliente_identidad`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `arqueo_caja`
--
ALTER TABLE `arqueo_caja`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cierres_caja`
--
ALTER TABLE `cierres_caja`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT de la tabla `configuracion`
--
ALTER TABLE `configuracion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `contratos`
--
ALTER TABLE `contratos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `cuotas_contrato`
--
ALTER TABLE `cuotas_contrato`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT de la tabla `detalle_devoluciones`
--
ALTER TABLE `detalle_devoluciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `detalle_entrada_inventario`
--
ALTER TABLE `detalle_entrada_inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT de la tabla `devoluciones`
--
ALTER TABLE `devoluciones`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT de la tabla `entradas_inventario`
--
ALTER TABLE `entradas_inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `pagos_venta`
--
ALTER TABLE `pagos_venta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT de la tabla `pagos_ventas`
--
ALTER TABLE `pagos_ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id_transaccion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=85;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `detalle_devoluciones`
--
ALTER TABLE `detalle_devoluciones`
  ADD CONSTRAINT `fk_detalle_dev` FOREIGN KEY (`devolucion_id`) REFERENCES `devoluciones` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_detalle_prod` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE;

--
-- Filtros para la tabla `detalle_entrada_inventario`
--
ALTER TABLE `detalle_entrada_inventario`
  ADD CONSTRAINT `detalle_entrada_inventario_ibfk_1` FOREIGN KEY (`entrada_id`) REFERENCES `entradas_inventario` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_entrada_inventario_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);

--
-- Filtros para la tabla `detalle_ventas`
--
ALTER TABLE `detalle_ventas`
  ADD CONSTRAINT `detalle_ventas_ibfk_1` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id_transaccion`) ON DELETE CASCADE,
  ADD CONSTRAINT `detalle_ventas_ibfk_2` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`);

--
-- Filtros para la tabla `devoluciones`
--
ALTER TABLE `devoluciones`
  ADD CONSTRAINT `fk_devolucion_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id_transaccion`) ON DELETE CASCADE;

--
-- Filtros para la tabla `entradas_inventario`
--
ALTER TABLE `entradas_inventario`
  ADD CONSTRAINT `entradas_inventario_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `ventas_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
