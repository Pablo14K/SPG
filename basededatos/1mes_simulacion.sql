-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: peluqueria_test
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
-- Table structure for table `asistencia`
--

DROP TABLE IF EXISTS `asistencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `asistencia` (
  `id_asistencia` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_turno` int(10) unsigned NOT NULL,
  `id_usuario_registro` int(10) unsigned DEFAULT NULL,
  `hora_entrada` time DEFAULT NULL,
  `hora_salida` time DEFAULT NULL,
  `motivo_ausencia` varchar(200) DEFAULT NULL,
  `horas_extras` decimal(5,2) NOT NULL DEFAULT 0.00,
  `observaciones` varchar(300) DEFAULT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `fecha` date NOT NULL,
  `justificada` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id_asistencia`),
  UNIQUE KEY `uq_asistencia_dia` (`id_turno`,`id_usuario`,`fecha`),
  KEY `idx_asistencia_registro` (`id_usuario_registro`),
  KEY `fk_asistencia_persona` (`id_usuario`),
  CONSTRAINT `fk_asistencia_persona` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_asistencia_turno` FOREIGN KEY (`id_turno`) REFERENCES `turno_laboral` (`id_turno`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_asistencia_usuario` FOREIGN KEY (`id_usuario_registro`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_asistencia_extras` CHECK (`horas_extras` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asistencia`
--

LOCK TABLES `asistencia` WRITE;
/*!40000 ALTER TABLE `asistencia` DISABLE KEYS */;
INSERT INTO `asistencia` VALUES (4,3,8,'13:08:54',NULL,NULL,0.00,NULL,8,'2026-08-08',NULL),(5,3,10,'13:08:55',NULL,NULL,0.00,NULL,10,'2026-08-08',NULL),(6,4,9,'13:08:56',NULL,NULL,0.00,NULL,9,'2026-08-08',NULL),(7,4,11,'13:08:56',NULL,NULL,0.00,NULL,11,'2026-08-08',NULL),(8,3,12,'13:11:49',NULL,NULL,0.00,NULL,8,'2026-07-10',NULL),(9,4,12,'13:11:49',NULL,NULL,0.00,NULL,9,'2026-07-10',NULL),(10,3,12,'13:11:50',NULL,NULL,0.00,NULL,10,'2026-07-10',NULL),(11,4,12,'13:11:50',NULL,NULL,0.00,NULL,11,'2026-07-10',NULL),(12,3,12,'13:11:52',NULL,NULL,0.00,NULL,8,'2026-07-11',NULL),(13,4,12,NULL,NULL,'Motivo registrado',0.00,NULL,9,'2026-07-11',0),(14,3,12,'13:11:52',NULL,NULL,0.00,NULL,10,'2026-07-11',NULL),(15,4,12,'13:11:52',NULL,NULL,0.00,NULL,11,'2026-07-11',NULL),(16,3,12,'13:11:54',NULL,NULL,0.00,NULL,8,'2026-07-13',NULL),(17,4,12,'13:11:54',NULL,NULL,0.00,NULL,9,'2026-07-13',NULL),(18,3,12,'13:11:54',NULL,NULL,0.00,NULL,10,'2026-07-13',NULL),(19,4,12,'13:11:54',NULL,NULL,0.00,NULL,11,'2026-07-13',NULL),(20,3,12,'13:11:55',NULL,NULL,0.00,NULL,8,'2026-07-14',NULL),(21,4,12,'13:11:55',NULL,NULL,0.00,NULL,9,'2026-07-14',NULL),(22,3,12,'13:11:55',NULL,NULL,0.00,NULL,10,'2026-07-14',NULL),(23,4,12,'13:11:55',NULL,NULL,0.00,NULL,11,'2026-07-14',NULL),(24,3,12,'13:11:57',NULL,NULL,0.00,NULL,8,'2026-07-15',NULL),(25,4,12,'13:11:57',NULL,NULL,0.00,NULL,9,'2026-07-15',NULL),(26,3,12,'13:11:57',NULL,NULL,0.00,NULL,10,'2026-07-15',NULL),(27,4,12,'13:11:57',NULL,NULL,0.00,NULL,11,'2026-07-15',NULL),(28,3,12,'13:11:58',NULL,NULL,0.00,NULL,8,'2026-07-16',NULL),(29,4,12,'13:11:58',NULL,NULL,0.00,NULL,9,'2026-07-16',NULL),(30,3,12,'13:11:58',NULL,NULL,0.00,NULL,10,'2026-07-16',NULL),(31,4,12,'13:11:59',NULL,NULL,0.00,NULL,11,'2026-07-16',NULL),(32,3,12,NULL,NULL,'Motivo registrado',0.00,NULL,8,'2026-07-17',1),(33,4,12,NULL,NULL,'Motivo registrado',0.00,NULL,9,'2026-07-17',1),(34,3,12,'13:12:00',NULL,NULL,0.00,NULL,10,'2026-07-17',NULL),(35,4,12,'13:12:01',NULL,NULL,0.00,NULL,11,'2026-07-17',NULL),(36,3,12,'13:12:02',NULL,NULL,0.00,NULL,8,'2026-07-18',NULL),(37,4,12,'13:12:02',NULL,NULL,0.00,NULL,9,'2026-07-18',NULL),(38,3,12,'13:12:02',NULL,NULL,0.00,NULL,10,'2026-07-18',NULL),(39,4,12,'13:12:02',NULL,NULL,0.00,NULL,11,'2026-07-18',NULL),(40,3,12,'13:12:04',NULL,NULL,0.00,NULL,8,'2026-07-20',NULL),(41,4,12,'13:12:04',NULL,NULL,0.00,NULL,9,'2026-07-20',NULL),(42,3,12,'13:12:04',NULL,NULL,0.00,NULL,10,'2026-07-20',NULL),(43,4,12,'13:12:04',NULL,NULL,0.00,NULL,11,'2026-07-20',NULL),(44,3,12,'13:12:05',NULL,NULL,0.00,NULL,8,'2026-07-21',NULL),(45,4,12,'13:12:05',NULL,NULL,0.00,NULL,9,'2026-07-21',NULL),(46,3,12,'13:12:06',NULL,NULL,0.00,NULL,10,'2026-07-21',NULL),(47,4,12,'13:12:06',NULL,NULL,0.00,NULL,11,'2026-07-21',NULL),(48,3,12,'13:12:07',NULL,NULL,0.00,NULL,8,'2026-07-22',NULL),(49,4,12,NULL,NULL,'Motivo registrado',0.00,NULL,9,'2026-07-22',1),(50,3,12,'13:12:07',NULL,NULL,0.00,NULL,10,'2026-07-22',NULL),(51,4,12,'13:12:07',NULL,NULL,0.00,NULL,11,'2026-07-22',NULL),(52,3,12,'13:12:09',NULL,NULL,0.00,NULL,8,'2026-07-23',NULL),(53,4,12,'13:12:09',NULL,NULL,0.00,NULL,9,'2026-07-23',NULL),(54,3,12,'13:12:09',NULL,NULL,0.00,NULL,10,'2026-07-23',NULL),(55,4,12,'13:12:09',NULL,NULL,0.00,NULL,11,'2026-07-23',NULL),(56,3,12,'13:12:11',NULL,NULL,0.00,NULL,8,'2026-07-24',NULL),(57,4,12,'13:12:11',NULL,NULL,0.00,NULL,9,'2026-07-24',NULL),(58,3,12,'13:12:11',NULL,NULL,0.00,NULL,10,'2026-07-24',NULL),(59,4,12,'13:12:11',NULL,NULL,0.00,NULL,11,'2026-07-24',NULL),(60,3,12,'13:12:13',NULL,NULL,0.00,NULL,8,'2026-07-25',NULL),(61,4,12,'13:12:13',NULL,NULL,0.00,NULL,9,'2026-07-25',NULL),(62,3,12,'13:12:13',NULL,NULL,0.00,NULL,10,'2026-07-25',NULL),(63,4,12,'13:12:13',NULL,NULL,0.00,NULL,11,'2026-07-25',NULL),(64,3,12,'13:12:15',NULL,NULL,0.00,NULL,8,'2026-07-27',NULL),(65,4,12,'13:12:15',NULL,NULL,0.00,NULL,9,'2026-07-27',NULL),(66,3,12,'13:12:16',NULL,NULL,0.00,NULL,10,'2026-07-27',NULL),(67,4,12,'13:12:16',NULL,NULL,0.00,NULL,11,'2026-07-27',NULL),(68,3,12,'13:12:16',NULL,NULL,0.00,NULL,8,'2026-07-28',NULL),(69,4,12,'13:12:17',NULL,NULL,0.00,NULL,9,'2026-07-28',NULL),(70,3,12,'13:12:17',NULL,NULL,0.00,NULL,10,'2026-07-28',NULL),(71,4,12,'13:12:17',NULL,NULL,0.00,NULL,11,'2026-07-28',NULL),(72,3,12,'13:12:18',NULL,NULL,0.00,NULL,8,'2026-07-29',NULL),(73,4,12,'13:12:18',NULL,NULL,0.00,NULL,9,'2026-07-29',NULL),(74,3,12,'13:12:18',NULL,NULL,0.00,NULL,10,'2026-07-29',NULL),(75,4,12,'13:12:19',NULL,NULL,0.00,NULL,11,'2026-07-29',NULL),(76,3,12,'13:12:20',NULL,NULL,0.00,NULL,8,'2026-07-30',NULL),(77,4,12,'13:12:20',NULL,NULL,0.00,NULL,9,'2026-07-30',NULL),(78,3,12,'13:12:20',NULL,NULL,0.00,NULL,10,'2026-07-30',NULL),(79,4,12,NULL,NULL,'Motivo registrado',0.00,NULL,11,'2026-07-30',1),(80,3,12,'13:12:22',NULL,NULL,0.00,NULL,8,'2026-07-31',NULL),(81,4,12,'13:12:22',NULL,NULL,0.00,NULL,9,'2026-07-31',NULL),(82,3,12,'13:12:23',NULL,NULL,0.00,NULL,10,'2026-07-31',NULL),(83,4,12,'13:12:23',NULL,NULL,0.00,NULL,11,'2026-07-31',NULL),(84,3,12,'13:12:24',NULL,NULL,0.00,NULL,8,'2026-08-01',NULL),(85,4,12,'13:12:24',NULL,NULL,0.00,NULL,9,'2026-08-01',NULL),(86,3,12,'13:12:25',NULL,NULL,0.00,NULL,10,'2026-08-01',NULL),(87,4,12,'13:12:25',NULL,NULL,0.00,NULL,11,'2026-08-01',NULL),(88,3,12,'13:12:27',NULL,NULL,0.00,NULL,8,'2026-08-03',NULL),(89,4,12,'13:12:28',NULL,NULL,0.00,NULL,9,'2026-08-03',NULL),(90,3,12,'13:12:28',NULL,NULL,0.00,NULL,10,'2026-08-03',NULL),(91,4,12,'13:12:28',NULL,NULL,0.00,NULL,11,'2026-08-03',NULL),(92,3,12,'13:12:28',NULL,NULL,0.00,NULL,8,'2026-08-04',NULL),(93,4,12,'13:12:29',NULL,NULL,0.00,NULL,9,'2026-08-04',NULL),(94,3,12,'13:12:29',NULL,NULL,0.00,NULL,10,'2026-08-04',NULL),(95,4,12,NULL,NULL,'Motivo registrado',0.00,NULL,11,'2026-08-04',1),(96,3,12,'13:12:30',NULL,NULL,0.00,NULL,8,'2026-08-05',NULL),(97,4,12,'13:12:30',NULL,NULL,0.00,NULL,9,'2026-08-05',NULL),(98,3,12,'13:12:31',NULL,NULL,0.00,NULL,10,'2026-08-05',NULL),(99,4,12,NULL,NULL,'Motivo registrado',0.00,NULL,11,'2026-08-05',0),(100,3,12,'13:12:32',NULL,NULL,0.00,NULL,8,'2026-08-06',NULL),(101,4,12,'13:12:32',NULL,NULL,0.00,NULL,9,'2026-08-06',NULL),(102,3,12,'13:12:32',NULL,NULL,0.00,NULL,10,'2026-08-06',NULL),(103,4,12,'13:12:32',NULL,NULL,0.00,NULL,11,'2026-08-06',NULL),(104,3,12,'13:12:34',NULL,NULL,0.00,NULL,8,'2026-08-07',NULL),(105,4,12,'13:12:34',NULL,NULL,0.00,NULL,9,'2026-08-07',NULL),(106,3,12,'13:12:34',NULL,NULL,0.00,NULL,10,'2026-08-07',NULL),(108,4,1,'16:59:57',NULL,NULL,0.00,NULL,11,'2026-08-07',NULL);
/*!40000 ALTER TABLE `asistencia` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auditoria`
--

DROP TABLE IF EXISTS `auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditoria` (
  `id_auditoria` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `accion` varchar(40) NOT NULL,
  `modulo` varchar(40) NOT NULL,
  `tabla_afectada` varchar(60) NOT NULL,
  `id_registro` int(10) unsigned DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `detalle` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`id_auditoria`),
  KEY `idx_aud_usuario` (`id_usuario`),
  KEY `idx_aud_fecha` (`fecha_hora`),
  KEY `idx_aud_tabla` (`tabla_afectada`),
  CONSTRAINT `fk_aud_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2260 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditoria`
--

LOCK TABLES `auditoria` WRITE;
/*!40000 ALTER TABLE `auditoria` DISABLE KEYS */;
INSERT INTO `auditoria` VALUES (1,1,'LOGIN','Seguridad','usuario',1,'2026-07-18 21:08:53','Inicio de sesión'),(2,1,'ALTA','Servicios','descuento',4,'2026-07-18 21:08:55','Promo Julio'),(3,1,'ALTA','Personal','comision',1,'2026-07-18 21:08:55','Comisión PORCENTAJE 30'),(4,1,'REGISTRO','Personal','asistencia',1,'2026-07-18 21:08:55','Asistencia del turno #1'),(5,1,'COMPRA','Inventario','compra',1,'2026-07-18 21:08:56','1 producto(s), 1 nuevo(s)'),(6,1,'CAMBIO_PASSWORD','Cuenta','usuario',1,'2026-07-18 21:08:56','El usuario cambió su contraseña'),(9,1,'LOGIN','Seguridad','usuario',1,'2026-07-18 21:43:24','Inicio de sesión'),(11,1,'LOGIN','Seguridad','usuario',1,'2026-07-18 21:48:39','Inicio de sesión'),(12,1,'MODIFICACION','Configuracion','rol_modulo',NULL,'2026-07-18 21:48:40','Actualizó permisos de roles'),(13,1,'LOGIN','Seguridad','usuario',1,'2026-07-18 21:51:24','Inicio de sesión'),(14,1,'ALTA','Configuracion','sucursal',2,'2026-07-18 21:51:24','Sucursal Centro'),(15,1,'LOGIN','Seguridad','usuario',1,'2026-07-18 21:53:45','Inicio de sesión'),(16,1,'EMISION','Facturacion','factura',1,'2026-07-18 21:53:46','Factura de la cita #1'),(17,1,'COBRO','Facturacion','factura',1,'2026-07-18 21:53:46','Cobro Gs. 40.000'),(18,6,'VERIFICACION','Seguridad','usuario',6,'2026-07-18 21:58:55','Cuenta verificada por correo'),(19,1,'LOGIN','Seguridad','usuario',1,'2026-07-18 21:59:18','Inicio de sesión'),(20,1,'LOGIN','Seguridad','usuario',1,'2026-07-26 22:22:58','Inicio de sesión'),(21,1,'ALTA','Configuracion','rol',5,'2026-07-26 22:23:15','Recepcion'),(22,1,'MODIFICACION','Configuracion','rol_modulo',NULL,'2026-07-26 22:23:15','Actualizó permisos de roles'),(23,1,'LOGIN','Seguridad','usuario',1,'2026-07-26 22:31:05','Inicio de sesión'),(24,1,'ATENCION','Citas','servicio_realizado',2,'2026-07-26 22:31:33','1 servicio(s), 1 producto(s) consumido(s)'),(25,1,'PAGO_PERSONAL','Facturacion','pago_personal',1,'2026-07-26 22:31:59','Liquidación 07/2026 (1 servicios)'),(26,1,'COMPRA','Inventario','compra',2,'2026-07-26 22:31:59','1 producto(s), 0 nuevo(s)'),(27,1,'PAGO_PROVEEDOR','Facturacion','compra',2,'2026-07-26 22:33:32','Pago Gs. 40.000'),(28,1,'REGISTRO','Personal','asistencia',2,'2026-07-26 22:33:58','Asistencia del turno #2'),(29,1,'REGISTRO','Personal','asistencia',2,'2026-07-26 22:33:59','Asistencia del turno #2'),(30,1,'MODIFICACION','Configuracion','categoria_producto',6,'2026-07-26 22:34:00','CatQA Renombrada'),(31,1,'BAJA','Configuracion','categoria_producto',6,'2026-07-26 22:34:00','Categoría eliminada'),(32,1,'LOGIN','Seguridad','usuario',1,'2026-07-26 22:35:47','Inicio de sesión'),(33,2,'LOGIN','Seguridad','usuario',2,'2026-07-26 22:35:59','Inicio de sesión'),(34,1,'LOGIN','Seguridad','usuario',1,'2026-07-26 23:04:45','Inicio de sesión'),(36,1,'LOGIN','Seguridad','usuario',1,'2026-08-04 09:03:02','Inicio de sesión'),(37,2,'LOGIN','Seguridad','usuario',2,'2026-08-04 09:03:57','Inicio de sesión'),(38,1,'LOGIN','Seguridad','usuario',1,'2026-08-04 09:05:17','Inicio de sesión'),(39,1,'LOGIN','Seguridad','usuario',1,'2026-08-04 20:22:58','Inicio de sesión'),(40,1,'LOGIN','Seguridad','usuario',1,'2026-08-04 21:31:40','Inicio de sesión'),(41,1,'LOGIN','Seguridad','usuario',1,'2026-08-04 21:40:36','Inicio de sesión'),(42,1,'LOGIN','Seguridad','usuario',1,'2026-08-04 21:47:54','Inicio de sesión'),(43,1,'LOGIN','Seguridad','usuario',1,'2026-08-04 21:57:02','Inicio de sesión'),(44,1,'LOGIN','Seguridad','usuario',1,'2026-08-04 22:01:37','Inicio de sesión'),(45,1,'CAJA_APERTURA','Facturacion','caja',2,'2026-08-04 22:18:15','Apertura con Gs. 100.000'),(46,1,'COMPRA','Inventario','compra',3,'2026-08-04 22:21:51','2 producto(s), 2 nuevo(s), total Gs. 70.000'),(47,1,'PAGO_PROVEEDOR','Facturacion','compra',3,'2026-08-04 22:22:14','Pago Gs. 70.000'),(48,1,'CAJA_CIERRE','Facturacion','caja',2,'2026-08-04 22:22:32','Cierre con saldo Gs. 30.000'),(49,1,'LOGIN','Seguridad','usuario',1,'2026-08-05 17:06:11','Inicio de sesión'),(50,1,'LOGIN','Seguridad','usuario',1,'2026-08-06 08:24:27','Inicio de sesión'),(51,2,'LOGIN','Seguridad','usuario',2,'2026-08-06 09:07:30','Inicio de sesión'),(52,1,'LOGIN','Seguridad','usuario',1,'2026-08-06 15:49:00','Inicio de sesión'),(53,1,'LOGIN','Seguridad','usuario',1,'2026-08-06 19:26:01','Inicio de sesión'),(54,1,'LOGIN','Seguridad','usuario',1,'2026-08-06 19:40:29','Inicio de sesión'),(55,1,'MODIFICACION','Configuracion','rol_modulo',NULL,'2026-08-06 19:43:51','Actualizó permisos de roles'),(56,1,'MODIFICACION','Configuracion','contacto_soporte',NULL,'2026-08-06 20:24:14','Canales: WHATSAPP, TELEGRAM'),(57,1,'LOGIN','Seguridad','usuario',1,'2026-08-07 09:19:53','Inicio de sesión'),(58,1,'LOGIN','Seguridad','usuario',1,'2026-08-07 09:25:11','Inicio de sesión'),(59,1,'LOGIN','Seguridad','usuario',1,'2026-08-07 17:42:01','Inicio de sesión'),(60,1,'LOGIN','Seguridad','usuario',1,'2026-08-07 18:03:42','Inicio de sesión'),(61,1,'LOGIN','Seguridad','usuario',1,'2026-08-07 18:04:16','Inicio de sesión'),(62,1,'LOGIN','Seguridad','usuario',1,'2026-08-07 18:17:08','Inicio de sesión'),(63,1,'LOGIN','Seguridad','usuario',1,'2026-08-08 12:50:06','Inicio de sesión'),(64,1,'LOGIN','Seguridad','usuario',1,'2026-08-08 13:00:31','Inicio de sesión'),(65,1,'ALTA','Personal','turno_laboral',3,'2026-08-08 13:00:31','Turno Mañana 08:00 a 12:30 · Lunes a Sábado'),(66,1,'ALTA','Personal','turno_laboral',4,'2026-08-08 13:00:31','Turno Tarde 13:30 a 19:00 · Lunes a Sábado'),(67,1,'ALTA','Configuracion','rol',6,'2026-08-08 13:00:32','Gerente'),(68,1,'ALTA','Personal','usuario',8,'2026-08-08 13:00:33','Lucía Benítez'),(69,1,'ALTA','Personal','usuario',9,'2026-08-08 13:00:33','Marta Cáceres'),(70,1,'ALTA','Personal','usuario',10,'2026-08-08 13:00:34','Rocío Duarte'),(71,1,'ALTA','Personal','usuario',11,'2026-08-08 13:00:34','Sofía Espínola'),(72,1,'ALTA','Personal','usuario',12,'2026-08-08 13:00:35','Carmen Fretes'),(73,1,'ALTA','Personal','usuario',13,'2026-08-08 13:00:35','Gloria Garay'),(74,1,'ALTA','Servicios','servicio',3,'2026-08-08 13:00:35','Corte de dama'),(75,1,'ALTA','Servicios','servicio',4,'2026-08-08 13:00:35','Corte de caballero'),(76,1,'ALTA','Servicios','servicio',5,'2026-08-08 13:00:36','Corte de niño'),(77,1,'ALTA','Servicios','servicio',6,'2026-08-08 13:00:36','Coloración completa'),(78,1,'ALTA','Servicios','servicio',7,'2026-08-08 13:00:36','Mechas / balayage'),(79,1,'ALTA','Servicios','servicio',8,'2026-08-08 13:00:36','Tratamiento capilar'),(80,1,'ALTA','Servicios','servicio',9,'2026-08-08 13:00:36','Keratina'),(81,1,'ALTA','Servicios','servicio',10,'2026-08-08 13:00:36','Brushing'),(82,1,'ALTA','Servicios','servicio',11,'2026-08-08 13:00:36','Peinado de fiesta'),(83,1,'ALTA','Servicios','servicio',12,'2026-08-08 13:00:37','Manicura'),(84,1,'ALTA','Servicios','servicio',13,'2026-08-08 13:00:37','Pedicura'),(85,1,'ALTA','Servicios','servicio',14,'2026-08-08 13:00:37','Lavado y acondicionado'),(86,1,'ALTA','Inventario','producto',5,'2026-08-08 13:00:37','Shampoo profesional 1L'),(87,1,'ALTA','Inventario','producto',6,'2026-08-08 13:00:37','Acondicionador 1L'),(88,1,'ALTA','Inventario','producto',7,'2026-08-08 13:00:37','Tintura profesional'),(89,1,'ALTA','Inventario','producto',8,'2026-08-08 13:00:37','Agua oxigenada 900ml'),(90,1,'ALTA','Inventario','producto',9,'2026-08-08 13:00:38','Guantes de latex (caja)'),(91,1,'ALTA','Inventario','producto',10,'2026-08-08 13:00:38','Serum reparador 100ml'),(92,1,'ALTA','Clientes','cliente',7,'2026-08-08 13:00:38','Andrea Villalba'),(93,1,'ALTA','Clientes','cliente',8,'2026-08-08 13:00:38','Beatriz Rojas'),(94,1,'ALTA','Clientes','cliente',9,'2026-08-08 13:00:38','Carla Mendoza'),(95,1,'ALTA','Clientes','cliente',10,'2026-08-08 13:00:38','Diana Ayala'),(96,1,'ALTA','Clientes','cliente',11,'2026-08-08 13:00:39','Elena Sanabria'),(97,1,'ALTA','Clientes','cliente',12,'2026-08-08 13:00:39','Fátima Ocampos'),(98,1,'ALTA','Clientes','cliente',13,'2026-08-08 13:00:39','Gabriela Riveros'),(99,1,'ALTA','Clientes','cliente',14,'2026-08-08 13:00:39','Hilda Cabrera'),(100,1,'ALTA','Clientes','cliente',15,'2026-08-08 13:00:39','Julia Bogado'),(101,1,'ALTA','Clientes','cliente',16,'2026-08-08 13:00:39','Mónica Paredes'),(102,1,'ALTA','Clientes','cliente',17,'2026-08-08 13:00:40','Norma Aquino'),(103,1,'ALTA','Clientes','cliente',18,'2026-08-08 13:00:40','Patricia Torres'),(104,1,'ALTA','Clientes','cliente',19,'2026-08-08 13:00:40','Rosa Vera'),(105,1,'ALTA','Clientes','cliente',20,'2026-08-08 13:00:40','Silvia Acosta'),(106,1,'ALTA','Clientes','cliente',21,'2026-08-08 13:00:40','Teresa Franco'),(107,1,'ALTA','Clientes','cliente',22,'2026-08-08 13:00:40','Ximena Ledesma'),(108,1,'ALTA','Clientes','cliente',23,'2026-08-08 13:00:40','Yolanda Barrios'),(109,1,'ALTA','Clientes','cliente',24,'2026-08-08 13:00:41','Zulma Escobar'),(110,1,'ALTA','Clientes','cliente',25,'2026-08-08 13:00:41','Javier Cardozo'),(111,1,'ALTA','Clientes','cliente',26,'2026-08-08 13:00:41','Marcos Segovia'),(112,1,'ALTA','Clientes','cliente',27,'2026-08-08 13:00:41','Rubén Maidana'),(113,1,'LOGIN','Seguridad','usuario',1,'2026-08-08 13:01:58','Inicio de sesión'),(114,1,'ALTA','Clientes','cliente',28,'2026-08-08 13:01:59','Irene Zárate'),(115,1,'ALTA','Clientes','cliente',29,'2026-08-08 13:01:59','Karina Núñez'),(116,1,'ALTA','Clientes','cliente',30,'2026-08-08 13:01:59','Laura Insfrán'),(117,1,'ALTA','Clientes','cliente',31,'2026-08-08 13:01:59','Olga Ramírez'),(118,1,'ALTA','Clientes','cliente',32,'2026-08-08 13:01:59','Verónica Giménez'),(119,1,'ALTA','Clientes','cliente',33,'2026-08-08 13:01:59','Wilma Martínez'),(120,1,'ALTA','Clientes','cliente',34,'2026-08-08 13:01:59','Óscar Britez'),(121,1,'LOGIN','Seguridad','usuario',1,'2026-08-08 13:03:20','Inicio de sesión'),(122,1,'COMPRA','Inventario','compra',4,'2026-08-08 13:03:21','4 producto(s), 0 nuevo(s), total Gs. 3.960.000'),(123,1,'COMPRA','Inventario','compra',5,'2026-08-08 13:03:21','2 producto(s), 0 nuevo(s), total Gs. 870.000'),(124,1,'COMPRA','Inventario','compra',6,'2026-08-08 13:03:21','1 producto(s), 1 nuevo(s), total Gs. 170.000'),(125,12,'LOGIN','Seguridad','usuario',12,'2026-08-08 13:05:24','Inicio de sesión'),(126,12,'ALTA','Citas','cita',3,'2026-08-08 13:05:25','Cita agendada para 2026-08-08 13:30:00'),(127,12,'ALTA','Citas','cita',4,'2026-08-08 13:05:25','Cita agendada para 2026-08-08 08:00:00'),(128,12,'ALTA','Citas','cita',5,'2026-08-08 13:05:25','Cita agendada para 2026-08-08 13:30:00'),(129,12,'ALTA','Citas','cita',6,'2026-08-08 13:05:25','Cita agendada para 2026-08-08 08:00:00'),(130,12,'ALTA','Citas','cita',7,'2026-08-08 13:05:25','Cita agendada para 2026-08-08 17:20:00'),(131,12,'ALTA','Citas','cita',8,'2026-08-08 13:05:25','Cita agendada para 2026-08-08 09:15:00'),(132,12,'ALTA','Citas','cita',9,'2026-08-08 13:05:25','Cita agendada para 2026-08-08 09:25:00'),(133,12,'ALTA','Citas','cita',10,'2026-08-08 13:05:26','Cita agendada para 2026-08-08 11:10:00'),(134,12,'ALTA','Citas','cita',11,'2026-08-08 13:05:26','Cita agendada para 2026-08-10 13:30:00'),(135,12,'ALTA','Citas','cita',12,'2026-08-08 13:05:26','Cita agendada para 2026-08-10 08:00:00'),(136,12,'ALTA','Citas','cita',13,'2026-08-08 13:05:26','Cita agendada para 2026-08-10 13:30:00'),(137,12,'ALTA','Citas','cita',14,'2026-08-08 13:05:26','Cita agendada para 2026-08-11 13:30:00'),(138,12,'ALTA','Citas','cita',15,'2026-08-08 13:05:26','Cita agendada para 2026-08-11 13:30:00'),(139,12,'ALTA','Citas','cita',16,'2026-08-08 13:05:26','Cita agendada para 2026-08-12 13:30:00'),(140,12,'ALTA','Citas','cita',17,'2026-08-08 13:05:26','Cita agendada para 2026-08-12 13:30:00'),(141,12,'ALTA','Citas','cita',18,'2026-08-08 13:05:27','Cita agendada para 2026-08-12 15:25:00'),(142,12,'ALTA','Citas','cita',19,'2026-08-08 13:05:27','Cita agendada para 2026-08-12 16:15:00'),(143,12,'ALTA','Citas','cita',20,'2026-08-08 13:05:27','Cita agendada para 2026-08-13 08:00:00'),(144,12,'ALTA','Citas','cita',21,'2026-08-08 13:05:27','Cita agendada para 2026-08-13 09:45:00'),(145,12,'ALTA','Citas','cita',22,'2026-08-08 13:05:27','Cita agendada para 2026-08-13 13:30:00'),(146,12,'ALTA','Citas','cita',23,'2026-08-08 13:05:27','Cita agendada para 2026-08-14 08:00:00'),(147,12,'ALTA','Citas','cita',24,'2026-08-08 13:05:27','Cita agendada para 2026-08-14 13:30:00'),(148,12,'ALTA','Citas','cita',25,'2026-08-08 13:05:28','Cita agendada para 2026-08-14 13:30:00'),(149,12,'ALTA','Citas','cita',26,'2026-08-08 13:05:28','Cita agendada para 2026-08-14 11:25:00'),(150,12,'ALTA','Citas','cita',27,'2026-08-08 13:05:28','Cita agendada para 2026-08-14 13:50:00'),(151,12,'ALTA','Citas','cita',28,'2026-08-08 13:05:28','Cita agendada para 2026-08-14 08:00:00'),(152,12,'ALTA','Citas','cita',29,'2026-08-08 13:05:28','Cita agendada para 2026-08-15 08:00:00'),(153,12,'ALTA','Citas','cita',30,'2026-08-08 13:05:28','Cita agendada para 2026-08-15 08:00:00'),(154,12,'ALTA','Citas','cita',31,'2026-08-08 13:05:28','Cita agendada para 2026-08-15 13:30:00'),(155,12,'ALTA','Citas','cita',32,'2026-08-08 13:05:28','Cita agendada para 2026-08-15 16:05:00'),(156,12,'ALTA','Citas','cita',33,'2026-08-08 13:05:29','Cita agendada para 2026-08-15 10:05:00'),(157,12,'ALTA','Citas','cita',34,'2026-08-08 13:05:29','Cita agendada para 2026-08-15 18:00:00'),(158,12,'ALTA','Citas','cita',35,'2026-08-08 13:05:29','Cita agendada para 2026-08-15 08:45:00'),(159,12,'ALTA','Citas','cita',36,'2026-08-08 13:05:29','Cita agendada para 2026-08-17 08:00:00'),(160,12,'ALTA','Citas','cita',37,'2026-08-08 13:05:29','Cita agendada para 2026-08-18 13:30:00'),(161,12,'ALTA','Citas','cita',38,'2026-08-08 13:05:29','Cita agendada para 2026-08-18 08:00:00'),(162,12,'ALTA','Citas','cita',39,'2026-08-08 13:05:29','Cita agendada para 2026-08-19 13:30:00'),(163,12,'ALTA','Citas','cita',40,'2026-08-08 13:05:30','Cita agendada para 2026-08-19 08:00:00'),(164,12,'ALTA','Citas','cita',41,'2026-08-08 13:05:30','Cita agendada para 2026-08-19 08:00:00'),(165,12,'ALTA','Citas','cita',42,'2026-08-08 13:05:30','Cita agendada para 2026-08-20 08:00:00'),(166,12,'ALTA','Citas','cita',43,'2026-08-08 13:05:30','Cita agendada para 2026-08-20 08:00:00'),(167,12,'ALTA','Citas','cita',44,'2026-08-08 13:05:30','Cita agendada para 2026-08-20 13:30:00'),(168,12,'ALTA','Citas','cita',45,'2026-08-08 13:05:30','Cita agendada para 2026-08-20 13:30:00'),(169,12,'ALTA','Citas','cita',46,'2026-08-08 13:05:30','Cita agendada para 2026-08-21 13:30:00'),(170,12,'ALTA','Citas','cita',47,'2026-08-08 13:06:13','Cita agendada para 2026-08-21 08:00:00'),(171,12,'ALTA','Citas','cita',48,'2026-08-08 13:06:14','Cita agendada para 2026-08-21 08:00:00'),(172,12,'ALTA','Citas','cita',49,'2026-08-08 13:06:14','Cita agendada para 2026-08-21 13:30:00'),(173,12,'ALTA','Citas','cita',50,'2026-08-08 13:06:14','Cita agendada para 2026-08-21 08:55:00'),(174,12,'ALTA','Citas','cita',51,'2026-08-08 13:06:14','Cita agendada para 2026-08-22 08:00:00'),(175,12,'ALTA','Citas','cita',52,'2026-08-08 13:06:14','Cita agendada para 2026-08-22 13:30:00'),(176,12,'ALTA','Citas','cita',53,'2026-08-08 13:06:14','Cita agendada para 2026-08-22 08:00:00'),(177,12,'ALTA','Citas','cita',54,'2026-08-08 13:06:14','Cita agendada para 2026-08-22 11:05:00'),(178,12,'ALTA','Citas','cita',55,'2026-08-08 13:06:15','Cita agendada para 2026-08-22 13:30:00'),(179,12,'ALTA','Citas','cita',56,'2026-08-08 13:06:15','Cita agendada para 2026-08-24 13:30:00'),(180,12,'ALTA','Citas','cita',57,'2026-08-08 13:06:15','Cita agendada para 2026-08-25 08:00:00'),(181,12,'ALTA','Citas','cita',58,'2026-08-08 13:06:15','Cita agendada para 2026-08-25 08:35:00'),(182,12,'ALTA','Citas','cita',59,'2026-08-08 13:06:15','Cita agendada para 2026-08-25 13:30:00'),(183,12,'ALTA','Citas','cita',60,'2026-08-08 13:06:15','Cita agendada para 2026-08-25 09:20:00'),(184,12,'ALTA','Citas','cita',61,'2026-08-08 13:06:15','Cita agendada para 2026-08-26 08:00:00'),(185,12,'ALTA','Citas','cita',62,'2026-08-08 13:06:16','Cita agendada para 2026-08-26 13:30:00'),(186,12,'ALTA','Citas','cita',63,'2026-08-08 13:06:16','Cita agendada para 2026-08-26 10:05:00'),(187,12,'ALTA','Citas','cita',64,'2026-08-08 13:06:16','Cita agendada para 2026-08-27 08:00:00'),(188,12,'ALTA','Citas','cita',65,'2026-08-08 13:06:16','Cita agendada para 2026-08-27 13:30:00'),(189,12,'ALTA','Citas','cita',66,'2026-08-08 13:06:16','Cita agendada para 2026-08-27 13:30:00'),(190,12,'ALTA','Citas','cita',67,'2026-08-08 13:06:16','Cita agendada para 2026-08-28 13:30:00'),(191,12,'ALTA','Citas','cita',68,'2026-08-08 13:06:16','Cita agendada para 2026-08-28 13:30:00'),(192,12,'ALTA','Citas','cita',69,'2026-08-08 13:06:17','Cita agendada para 2026-08-28 14:05:00'),(193,12,'ALTA','Citas','cita',70,'2026-08-08 13:06:17','Cita agendada para 2026-08-28 15:30:00'),(194,12,'ALTA','Citas','cita',71,'2026-08-08 13:06:17','Cita agendada para 2026-08-28 16:35:00'),(195,12,'ALTA','Citas','cita',72,'2026-08-08 13:06:17','Cita agendada para 2026-08-29 08:00:00'),(196,12,'ALTA','Citas','cita',73,'2026-08-08 13:06:17','Cita agendada para 2026-08-29 13:30:00'),(197,12,'ALTA','Citas','cita',74,'2026-08-08 13:06:17','Cita agendada para 2026-08-29 10:05:00'),(198,12,'ALTA','Citas','cita',75,'2026-08-08 13:06:17','Cita agendada para 2026-08-29 11:10:00'),(199,12,'ALTA','Citas','cita',76,'2026-08-08 13:06:18','Cita agendada para 2026-08-29 16:05:00'),(200,12,'ALTA','Citas','cita',77,'2026-08-08 13:06:18','Cita agendada para 2026-08-31 13:30:00'),(201,12,'ALTA','Citas','cita',78,'2026-08-08 13:06:18','Cita agendada para 2026-08-31 13:30:00'),(202,12,'ALTA','Citas','cita',79,'2026-08-08 13:06:18','Cita agendada para 2026-08-31 14:50:00'),(203,12,'ALTA','Citas','cita',80,'2026-08-08 13:06:18','Cita agendada para 2026-09-01 13:30:00'),(204,12,'ALTA','Citas','cita',81,'2026-08-08 13:06:19','Cita agendada para 2026-09-01 08:00:00'),(205,12,'ALTA','Citas','cita',82,'2026-08-08 13:06:19','Cita agendada para 2026-09-01 08:00:00'),(206,12,'ALTA','Citas','cita',83,'2026-08-08 13:06:19','Cita agendada para 2026-09-02 08:00:00'),(207,12,'ALTA','Citas','cita',84,'2026-08-08 13:06:19','Cita agendada para 2026-09-02 13:30:00'),(208,12,'ALTA','Citas','cita',85,'2026-08-08 13:06:19','Cita agendada para 2026-09-03 13:30:00'),(209,12,'ALTA','Citas','cita',86,'2026-08-08 13:06:19','Cita agendada para 2026-09-03 14:35:00'),(210,12,'ALTA','Citas','cita',87,'2026-08-08 13:06:20','Cita agendada para 2026-09-03 15:40:00'),(211,12,'ALTA','Citas','cita',88,'2026-08-08 13:06:20','Cita agendada para 2026-09-04 08:00:00'),(212,12,'ALTA','Citas','cita',89,'2026-08-08 13:06:20','Cita agendada para 2026-09-04 13:30:00'),(213,12,'ALTA','Citas','cita',90,'2026-08-08 13:06:20','Cita agendada para 2026-09-04 16:35:00'),(214,12,'ALTA','Citas','cita',91,'2026-08-08 13:06:20','Cita agendada para 2026-09-04 17:55:00'),(215,12,'ALTA','Citas','cita',92,'2026-08-08 13:06:21','Cita agendada para 2026-09-05 08:00:00'),(216,12,'ALTA','Citas','cita',93,'2026-08-08 13:06:21','Cita agendada para 2026-09-05 13:30:00'),(217,12,'ALTA','Citas','cita',94,'2026-08-08 13:06:21','Cita agendada para 2026-09-05 08:00:00'),(218,12,'ALTA','Citas','cita',95,'2026-08-08 13:06:22','Cita agendada para 2026-09-05 10:35:00'),(219,12,'ALTA','Citas','cita',96,'2026-08-08 13:06:22','Cita agendada para 2026-09-05 13:35:00'),(220,1,'LOGIN','Seguridad','usuario',1,'2026-08-08 13:08:53','Inicio de sesión'),(221,1,'CAJA_APERTURA','Facturacion','caja',3,'2026-08-08 13:08:53','Apertura con Gs. 500.000'),(222,8,'LOGIN','Seguridad','usuario',8,'2026-08-08 13:08:54','Inicio de sesión'),(223,10,'LOGIN','Seguridad','usuario',10,'2026-08-08 13:08:55','Inicio de sesión'),(224,9,'LOGIN','Seguridad','usuario',9,'2026-08-08 13:08:55','Inicio de sesión'),(225,11,'LOGIN','Seguridad','usuario',11,'2026-08-08 13:08:56','Inicio de sesión'),(226,10,'LOGIN','Seguridad','usuario',10,'2026-08-08 13:08:56','Inicio de sesión'),(227,10,'ATENCION','Citas','servicio_realizado',4,'2026-08-08 13:08:57','2 servicio(s), 1 producto(s) consumido(s)'),(228,8,'LOGIN','Seguridad','usuario',8,'2026-08-08 13:08:57','Inicio de sesión'),(229,8,'ATENCION','Citas','servicio_realizado',6,'2026-08-08 13:08:58','2 servicio(s), 2 producto(s) consumido(s)'),(230,8,'LOGIN','Seguridad','usuario',8,'2026-08-08 13:08:58','Inicio de sesión'),(231,8,'ATENCION','Citas','servicio_realizado',8,'2026-08-08 13:08:58','2 servicio(s), 2 producto(s) consumido(s)'),(232,10,'LOGIN','Seguridad','usuario',10,'2026-08-08 13:08:59','Inicio de sesión'),(233,10,'ATENCION','Citas','servicio_realizado',9,'2026-08-08 13:08:59','1 servicio(s), 1 producto(s) consumido(s)'),(234,8,'LOGIN','Seguridad','usuario',8,'2026-08-08 13:09:00','Inicio de sesión'),(235,8,'ATENCION','Citas','servicio_realizado',10,'2026-08-08 13:09:00','2 servicio(s), 1 producto(s) consumido(s)'),(236,11,'LOGIN','Seguridad','usuario',11,'2026-08-08 13:09:00','Inicio de sesión'),(237,11,'ATENCION','Citas','servicio_realizado',3,'2026-08-08 13:09:01','2 servicio(s), 1 producto(s) consumido(s)'),(238,9,'LOGIN','Seguridad','usuario',9,'2026-08-08 13:09:01','Inicio de sesión'),(239,9,'ATENCION','Citas','servicio_realizado',5,'2026-08-08 13:09:01','3 servicio(s), 1 producto(s) consumido(s)'),(240,9,'LOGIN','Seguridad','usuario',9,'2026-08-08 13:09:02','Inicio de sesión'),(241,9,'ATENCION','Citas','servicio_realizado',7,'2026-08-08 13:09:02','1 servicio(s), 1 producto(s) consumido(s)'),(242,12,'LOGIN','Seguridad','usuario',12,'2026-08-08 13:09:03','Inicio de sesión'),(243,12,'EMISION','Facturacion','factura',2,'2026-08-08 13:09:03','Comprobante 001-001-0000001 de la cita #4'),(244,12,'EMISION','Facturacion','factura',3,'2026-08-08 13:09:03','Comprobante 001-001-0000002 de la cita #6'),(245,12,'EMISION','Facturacion','factura',4,'2026-08-08 13:09:03','Comprobante 001-001-0000003 de la cita #8'),(246,12,'EMISION','Facturacion','factura',5,'2026-08-08 13:09:03','Comprobante 001-001-0000004 de la cita #9'),(247,12,'EMISION','Facturacion','factura',6,'2026-08-08 13:09:03','Comprobante 001-001-0000005 de la cita #10'),(248,12,'EMISION','Facturacion','factura',7,'2026-08-08 13:09:04','Comprobante 001-001-0000006 de la cita #3'),(249,12,'EMISION','Facturacion','factura',8,'2026-08-08 13:09:04','Comprobante 001-001-0000007 de la cita #5'),(250,12,'EMISION','Facturacion','factura',9,'2026-08-08 13:09:04','Comprobante 001-001-0000008 de la cita #7'),(251,12,'COBRO','Facturacion','factura',2,'2026-08-08 13:09:04','Cobro Gs. 115.000'),(252,12,'COBRO','Facturacion','factura',3,'2026-08-08 13:09:04','Cobro Gs. 100.000'),(253,12,'COBRO','Facturacion','factura',4,'2026-08-08 13:09:04','Cobro Gs. 115.000'),(254,12,'COBRO','Facturacion','factura',5,'2026-08-08 13:09:04','Cobro Gs. 40.000 en 2 medios: Efectivo Gs. 20.000 + Tarjeta de debito Gs. 20.000'),(255,12,'COBRO','Facturacion','factura',6,'2026-08-08 13:09:05','Cobro Gs. 90.000'),(256,12,'COBRO','Facturacion','factura',7,'2026-08-08 13:09:05','Cobro Gs. 220.000'),(257,12,'COBRO','Facturacion','factura',8,'2026-08-08 13:09:05','Cobro Gs. 465.000'),(258,12,'COBRO','Facturacion','factura',9,'2026-08-08 13:09:05','Cobro Gs. 150.000 en 2 medios: Efectivo Gs. 75.000 + Tarjeta de debito Gs. 75.000'),(259,12,'LOGIN','Seguridad','usuario',12,'2026-08-08 13:11:49','Inicio de sesión'),(260,12,'ALTA','Citas','cita',97,'2026-08-08 13:11:50','Cita agendada para 2026-08-08 09:30:00'),(261,12,'ATENCION','Citas','servicio_realizado',97,'2026-08-08 13:11:50','1 servicio(s), 1 producto(s) consumido(s)'),(262,12,'EMISION','Facturacion','factura',10,'2026-08-08 13:11:50','Comprobante 001-001-0000009 de la cita #97'),(263,12,'COBRO','Facturacion','factura',10,'2026-08-08 13:11:50','Cobro Gs. 50.000'),(264,12,'ALTA','Citas','cita',98,'2026-08-08 13:11:50','Cita agendada para 2026-08-08 09:30:00'),(265,12,'ATENCION','Citas','servicio_realizado',98,'2026-08-08 13:11:51','1 servicio(s), 1 producto(s) consumido(s)'),(266,12,'EMISION','Facturacion','factura',11,'2026-08-08 13:11:51','Comprobante 001-001-0000010 de la cita #98'),(267,12,'COBRO','Facturacion','factura',11,'2026-08-08 13:11:51','Cobro Gs. 50.000'),(268,12,'ALTA','Citas','cita',99,'2026-08-08 13:11:51','Cita agendada para 2026-08-08 15:00:00'),(269,12,'ATENCION','Citas','servicio_realizado',99,'2026-08-08 13:11:51','2 servicio(s), 2 producto(s) consumido(s)'),(270,12,'EMISION','Facturacion','factura',12,'2026-08-08 13:11:51','Comprobante 001-001-0000011 de la cita #99'),(271,12,'COBRO','Facturacion','factura',12,'2026-08-08 13:11:52','Cobro Gs. 330.000'),(272,12,'ALTA','Citas','cita',100,'2026-08-08 13:11:52','Cita agendada para 2026-08-08 15:00:00'),(273,12,'ATENCION','Citas','servicio_realizado',100,'2026-08-08 13:11:52','1 servicio(s), 2 producto(s) consumido(s)'),(274,12,'EMISION','Facturacion','factura',13,'2026-08-08 13:11:52','Comprobante 001-001-0000012 de la cita #100'),(275,12,'AUSENCIA','Personal','asistencia',9,'2026-08-08 13:11:52','Marta Cáceres ausente el 2026-07-11 — sin permiso: Motivo registrado'),(276,12,'ALTA','Citas','cita',101,'2026-08-08 13:11:53','Cita agendada para 2026-08-08 15:00:00'),(277,12,'CANCELACION','Citas','cita',101,'2026-08-08 13:11:53','Cita cancelada'),(278,12,'ALTA','Citas','cita',102,'2026-08-08 13:11:53','Cita agendada para 2026-08-08 09:30:00'),(279,12,'ATENCION','Citas','servicio_realizado',102,'2026-08-08 13:11:53','2 servicio(s), 1 producto(s) consumido(s)'),(280,12,'EMISION','Facturacion','factura',14,'2026-08-08 13:11:53','Comprobante 001-001-0000013 de la cita #102'),(281,12,'COBRO','Facturacion','factura',14,'2026-08-08 13:11:53','Cobro Gs. 115.000'),(282,12,'ALTA','Citas','cita',103,'2026-08-08 13:11:54','Cita agendada para 2026-08-08 11:00:00'),(283,12,'ATENCION','Citas','servicio_realizado',103,'2026-08-08 13:11:54','1 servicio(s), 1 producto(s) consumido(s)'),(284,12,'EMISION','Facturacion','factura',15,'2026-08-08 13:11:54','Comprobante 001-001-0000014 de la cita #103'),(285,12,'COBRO','Facturacion','factura',15,'2026-08-08 13:11:54','Cobro Gs. 75.000 en 2 medios: Efectivo Gs. 45.000 + Tarjeta de debito Gs. 30.000'),(286,12,'ALTA','Citas','cita',104,'2026-08-08 13:11:54','Cita agendada para 2026-08-08 15:00:00'),(287,12,'MODIFICACION','Citas','cita',104,'2026-08-08 13:11:55','Estado cambiado a Ausente'),(288,12,'ALTA','Citas','cita',105,'2026-08-08 13:11:55','Cita agendada para 2026-08-08 09:30:00'),(289,12,'ATENCION','Citas','servicio_realizado',105,'2026-08-08 13:11:55','1 servicio(s), 2 producto(s) consumido(s)'),(290,12,'EMISION','Facturacion','factura',16,'2026-08-08 13:11:55','Comprobante 001-001-0000015 de la cita #105'),(291,12,'COBRO','Facturacion','factura',16,'2026-08-08 13:11:55','Cobro Gs. 40.000'),(292,12,'ALTA','Citas','cita',106,'2026-08-08 13:11:56','Cita agendada para 2026-08-08 15:00:00'),(293,12,'ALTA','Citas','cita',107,'2026-08-08 13:11:56','Cita agendada para 2026-08-08 09:30:00'),(294,12,'ATENCION','Citas','servicio_realizado',107,'2026-08-08 13:11:56','1 servicio(s), 2 producto(s) consumido(s)'),(295,12,'EMISION','Facturacion','factura',17,'2026-08-08 13:11:56','Comprobante 001-001-0000016 de la cita #107'),(296,12,'COBRO','Facturacion','factura',17,'2026-08-08 13:11:56','Cobro Gs. 75.000'),(297,12,'ALTA','Citas','cita',108,'2026-08-08 13:11:56','Cita agendada para 2026-08-08 11:00:00'),(298,12,'ATENCION','Citas','servicio_realizado',108,'2026-08-08 13:11:56','2 servicio(s), 1 producto(s) consumido(s)'),(299,12,'EMISION','Facturacion','factura',18,'2026-08-08 13:11:57','Comprobante 001-001-0000017 de la cita #108'),(300,12,'COBRO','Facturacion','factura',18,'2026-08-08 13:11:57','Cobro Gs. 125.000 en 2 medios: Efectivo Gs. 75.000 + Tarjeta de debito Gs. 50.000'),(301,12,'ALTA','Citas','cita',109,'2026-08-08 13:11:57','Cita agendada para 2026-08-08 09:30:00'),(302,12,'MODIFICACION','Citas','cita',109,'2026-08-08 13:11:57','Estado cambiado a Ausente'),(303,12,'ALTA','Citas','cita',110,'2026-08-08 13:11:57','Cita agendada para 2026-08-08 15:00:00'),(304,12,'ALTA','Citas','cita',111,'2026-08-08 13:11:58','Cita agendada para 2026-08-08 16:30:00'),(305,12,'ATENCION','Citas','servicio_realizado',111,'2026-08-08 13:11:58','1 servicio(s), 1 producto(s) consumido(s)'),(306,12,'EMISION','Facturacion','factura',19,'2026-08-08 13:11:58','Comprobante 001-001-0000018 de la cita #111'),(307,12,'COBRO','Facturacion','factura',19,'2026-08-08 13:11:58','Cobro Gs. 75.000'),(308,12,'ALTA','Citas','cita',112,'2026-08-08 13:11:59','Cita agendada para 2026-08-08 15:00:00'),(309,12,'ATENCION','Citas','servicio_realizado',112,'2026-08-08 13:11:59','1 servicio(s), 2 producto(s) consumido(s)'),(310,12,'EMISION','Facturacion','factura',20,'2026-08-08 13:11:59','Comprobante 001-001-0000019 de la cita #112'),(311,12,'COBRO','Facturacion','factura',20,'2026-08-08 13:11:59','Cobro Gs. 350.000'),(312,12,'ALTA','Citas','cita',113,'2026-08-08 13:11:59','Cita agendada para 2026-08-08 15:00:00'),(313,12,'ATENCION','Citas','servicio_realizado',113,'2026-08-08 13:11:59','2 servicio(s), 2 producto(s) consumido(s)'),(314,12,'EMISION','Facturacion','factura',21,'2026-08-08 13:11:59','Comprobante 001-001-0000020 de la cita #113'),(315,12,'COBRO','Facturacion','factura',21,'2026-08-08 13:12:00','Cobro Gs. 205.000'),(316,12,'ALTA','Citas','cita',114,'2026-08-08 13:12:00','Cita agendada para 2026-08-08 16:30:00'),(317,12,'ATENCION','Citas','servicio_realizado',114,'2026-08-08 13:12:00','1 servicio(s), 1 producto(s) consumido(s)'),(318,12,'EMISION','Facturacion','factura',22,'2026-08-08 13:12:00','Comprobante 001-001-0000021 de la cita #114'),(319,12,'AUSENCIA','Personal','asistencia',8,'2026-08-08 13:12:00','Lucía Benítez ausente el 2026-07-17 — con permiso: Motivo registrado'),(320,12,'AUSENCIA','Personal','asistencia',9,'2026-08-08 13:12:00','Marta Cáceres ausente el 2026-07-17 — con permiso: Motivo registrado'),(321,12,'ALTA','Citas','cita',115,'2026-08-08 13:12:01','Cita agendada para 2026-08-08 09:30:00'),(322,12,'ATENCION','Citas','servicio_realizado',115,'2026-08-08 13:12:01','2 servicio(s), 2 producto(s) consumido(s)'),(323,12,'EMISION','Facturacion','factura',23,'2026-08-08 13:12:01','Comprobante 001-001-0000022 de la cita #115'),(324,12,'COBRO','Facturacion','factura',23,'2026-08-08 13:12:01','Cobro Gs. 105.000'),(325,12,'ALTA','Citas','cita',116,'2026-08-08 13:12:01','Cita agendada para 2026-08-08 15:00:00'),(326,12,'ALTA','Citas','cita',117,'2026-08-08 13:12:02','Cita agendada para 2026-08-08 16:30:00'),(327,12,'ALTA','Citas','cita',118,'2026-08-08 13:12:03','Cita agendada para 2026-08-08 15:00:00'),(328,12,'ATENCION','Citas','servicio_realizado',118,'2026-08-08 13:12:03','1 servicio(s), 1 producto(s) consumido(s)'),(329,12,'EMISION','Facturacion','factura',24,'2026-08-08 13:12:03','Comprobante 001-001-0000023 de la cita #118'),(330,12,'ALTA','Citas','cita',119,'2026-08-08 13:12:03','Cita agendada para 2026-08-08 09:30:00'),(331,12,'CANCELACION','Citas','cita',119,'2026-08-08 13:12:03','Cita cancelada'),(332,12,'ALTA','Citas','cita',120,'2026-08-08 13:12:03','Cita agendada para 2026-08-08 16:30:00'),(333,12,'ATENCION','Citas','servicio_realizado',120,'2026-08-08 13:12:03','2 servicio(s), 2 producto(s) consumido(s)'),(334,12,'EMISION','Facturacion','factura',25,'2026-08-08 13:12:04','Comprobante 001-001-0000024 de la cita #120'),(335,12,'COBRO','Facturacion','factura',25,'2026-08-08 13:12:04','Cobro Gs. 135.000'),(336,12,'ALTA','Citas','cita',121,'2026-08-08 13:12:04','Cita agendada para 2026-08-08 09:30:00'),(337,12,'ATENCION','Citas','servicio_realizado',121,'2026-08-08 13:12:04','2 servicio(s), 2 producto(s) consumido(s)'),(338,12,'EMISION','Facturacion','factura',26,'2026-08-08 13:12:05','Comprobante 001-001-0000025 de la cita #121'),(339,12,'COBRO','Facturacion','factura',26,'2026-08-08 13:12:05','Cobro Gs. 110.000'),(340,12,'ALTA','Citas','cita',122,'2026-08-08 13:12:05','Cita agendada para 2026-08-08 09:30:00'),(341,12,'ATENCION','Citas','servicio_realizado',122,'2026-08-08 13:12:05','2 servicio(s), 1 producto(s) consumido(s)'),(342,12,'EMISION','Facturacion','factura',27,'2026-08-08 13:12:05','Comprobante 001-001-0000026 de la cita #122'),(343,12,'COBRO','Facturacion','factura',27,'2026-08-08 13:12:05','Cobro Gs. 135.000'),(344,12,'ALTA','Citas','cita',123,'2026-08-08 13:12:06','Cita agendada para 2026-08-08 09:30:00'),(345,12,'ATENCION','Citas','servicio_realizado',123,'2026-08-08 13:12:06','2 servicio(s), 2 producto(s) consumido(s)'),(346,12,'EMISION','Facturacion','factura',28,'2026-08-08 13:12:06','Comprobante 001-001-0000027 de la cita #123'),(347,12,'COBRO','Facturacion','factura',28,'2026-08-08 13:12:06','Cobro Gs. 125.000'),(348,12,'ALTA','Citas','cita',124,'2026-08-08 13:12:06','Cita agendada para 2026-08-08 15:00:00'),(349,12,'ATENCION','Citas','servicio_realizado',124,'2026-08-08 13:12:06','1 servicio(s), 1 producto(s) consumido(s)'),(350,12,'EMISION','Facturacion','factura',29,'2026-08-08 13:12:07','Comprobante 001-001-0000028 de la cita #124'),(351,12,'COBRO','Facturacion','factura',29,'2026-08-08 13:12:07','Cobro Gs. 420.000'),(352,12,'AUSENCIA','Personal','asistencia',9,'2026-08-08 13:12:07','Marta Cáceres ausente el 2026-07-22 — con permiso: Motivo registrado'),(353,12,'ALTA','Citas','cita',125,'2026-08-08 13:12:07','Cita agendada para 2026-08-08 15:00:00'),(354,12,'ATENCION','Citas','servicio_realizado',125,'2026-08-08 13:12:08','2 servicio(s), 1 producto(s) consumido(s)'),(355,12,'EMISION','Facturacion','factura',30,'2026-08-08 13:12:08','Comprobante 001-001-0000029 de la cita #125'),(356,12,'ALTA','Citas','cita',126,'2026-08-08 13:12:08','Cita agendada para 2026-08-08 09:30:00'),(357,12,'ATENCION','Citas','servicio_realizado',126,'2026-08-08 13:12:08','2 servicio(s), 2 producto(s) consumido(s)'),(358,12,'EMISION','Facturacion','factura',31,'2026-08-08 13:12:08','Comprobante 001-001-0000030 de la cita #126'),(359,12,'COBRO','Facturacion','factura',31,'2026-08-08 13:12:08','Cobro Gs. 100.000 en 2 medios: Efectivo Gs. 60.000 + Tarjeta de debito Gs. 40.000'),(360,12,'ALTA','Citas','cita',127,'2026-08-08 13:12:08','Cita agendada para 2026-08-08 16:30:00'),(361,12,'ATENCION','Citas','servicio_realizado',127,'2026-08-08 13:12:09','1 servicio(s), 1 producto(s) consumido(s)'),(362,12,'EMISION','Facturacion','factura',32,'2026-08-08 13:12:09','Comprobante 001-001-0000031 de la cita #127'),(363,12,'COBRO','Facturacion','factura',32,'2026-08-08 13:12:09','Cobro Gs. 280.000'),(364,12,'ALTA','Citas','cita',128,'2026-08-08 13:12:09','Cita agendada para 2026-08-08 09:30:00'),(365,12,'ATENCION','Citas','servicio_realizado',128,'2026-08-08 13:12:09','1 servicio(s), 2 producto(s) consumido(s)'),(366,12,'EMISION','Facturacion','factura',33,'2026-08-08 13:12:10','Comprobante 001-001-0000032 de la cita #128'),(367,12,'COBRO','Facturacion','factura',33,'2026-08-08 13:12:10','Cobro Gs. 420.000'),(368,12,'ALTA','Citas','cita',129,'2026-08-08 13:12:10','Cita agendada para 2026-08-08 09:30:00'),(369,12,'ATENCION','Citas','servicio_realizado',129,'2026-08-08 13:12:10','2 servicio(s), 1 producto(s) consumido(s)'),(370,12,'EMISION','Facturacion','factura',34,'2026-08-08 13:12:10','Comprobante 001-001-0000033 de la cita #129'),(371,12,'COBRO','Facturacion','factura',34,'2026-08-08 13:12:10','Cobro Gs. 90.000'),(372,12,'ALTA','Citas','cita',130,'2026-08-08 13:12:11','Cita agendada para 2026-08-08 15:00:00'),(373,12,'ATENCION','Citas','servicio_realizado',130,'2026-08-08 13:12:11','1 servicio(s), 1 producto(s) consumido(s)'),(374,12,'EMISION','Facturacion','factura',35,'2026-08-08 13:12:11','Comprobante 001-001-0000034 de la cita #130'),(375,12,'COBRO','Facturacion','factura',35,'2026-08-08 13:12:11','Cobro Gs. 280.000'),(376,12,'ALTA','Citas','cita',131,'2026-08-08 13:12:11','Cita agendada para 2026-08-08 09:30:00'),(377,12,'ATENCION','Citas','servicio_realizado',131,'2026-08-08 13:12:12','2 servicio(s), 1 producto(s) consumido(s)'),(378,12,'EMISION','Facturacion','factura',36,'2026-08-08 13:12:12','Comprobante 001-001-0000035 de la cita #131'),(379,12,'COBRO','Facturacion','factura',36,'2026-08-08 13:12:12','Cobro Gs. 95.000'),(380,12,'ALTA','Citas','cita',132,'2026-08-08 13:12:12','Cita agendada para 2026-08-08 09:30:00'),(381,12,'ATENCION','Citas','servicio_realizado',132,'2026-08-08 13:12:12','1 servicio(s), 2 producto(s) consumido(s)'),(382,12,'EMISION','Facturacion','factura',37,'2026-08-08 13:12:12','Comprobante 001-001-0000036 de la cita #132'),(383,12,'COBRO','Facturacion','factura',37,'2026-08-08 13:12:12','Cobro Gs. 25.000'),(384,12,'ALTA','Citas','cita',133,'2026-08-08 13:12:13','Cita agendada para 2026-08-08 15:00:00'),(385,12,'ALTA','Citas','cita',134,'2026-08-08 13:12:13','Cita agendada para 2026-08-08 15:00:00'),(386,12,'ATENCION','Citas','servicio_realizado',134,'2026-08-08 13:12:14','1 servicio(s), 2 producto(s) consumido(s)'),(387,12,'EMISION','Facturacion','factura',38,'2026-08-08 13:12:14','Comprobante 001-001-0000037 de la cita #134'),(388,12,'COBRO','Facturacion','factura',38,'2026-08-08 13:12:14','Cobro Gs. 280.000'),(389,12,'ALTA','Citas','cita',135,'2026-08-08 13:12:14','Cita agendada para 2026-08-08 09:30:00'),(390,12,'ATENCION','Citas','servicio_realizado',135,'2026-08-08 13:12:14','1 servicio(s), 2 producto(s) consumido(s)'),(391,12,'EMISION','Facturacion','factura',39,'2026-08-08 13:12:14','Comprobante 001-001-0000038 de la cita #135'),(392,12,'COBRO','Facturacion','factura',39,'2026-08-08 13:12:14','Cobro Gs. 55.000'),(393,12,'ALTA','Citas','cita',136,'2026-08-08 13:12:15','Cita agendada para 2026-08-08 16:30:00'),(394,12,'ATENCION','Citas','servicio_realizado',136,'2026-08-08 13:12:15','1 servicio(s), 1 producto(s) consumido(s)'),(395,12,'EMISION','Facturacion','factura',40,'2026-08-08 13:12:15','Comprobante 001-001-0000039 de la cita #136'),(396,12,'COBRO','Facturacion','factura',40,'2026-08-08 13:12:15','Cobro Gs. 55.000'),(397,12,'ALTA','Citas','cita',137,'2026-08-08 13:12:16','Cita agendada para 2026-08-08 15:00:00'),(398,12,'ATENCION','Citas','servicio_realizado',137,'2026-08-08 13:12:16','2 servicio(s), 1 producto(s) consumido(s)'),(399,12,'EMISION','Facturacion','factura',41,'2026-08-08 13:12:16','Comprobante 001-001-0000040 de la cita #137'),(400,12,'COBRO','Facturacion','factura',41,'2026-08-08 13:12:16','Cobro Gs. 470.000'),(401,12,'ALTA','Citas','cita',138,'2026-08-08 13:12:16','Cita agendada para 2026-08-08 15:00:00'),(402,12,'MODIFICACION','Citas','cita',138,'2026-08-08 13:12:16','Estado cambiado a Ausente'),(403,12,'ALTA','Citas','cita',139,'2026-08-08 13:12:17','Cita agendada para 2026-08-08 09:30:00'),(404,12,'ALTA','Citas','cita',140,'2026-08-08 13:12:17','Cita agendada para 2026-08-08 15:00:00'),(405,12,'ATENCION','Citas','servicio_realizado',140,'2026-08-08 13:12:17','2 servicio(s), 1 producto(s) consumido(s)'),(406,12,'EMISION','Facturacion','factura',42,'2026-08-08 13:12:17','Comprobante 001-001-0000041 de la cita #140'),(407,12,'COBRO','Facturacion','factura',42,'2026-08-08 13:12:18','Cobro Gs. 390.000'),(408,12,'ALTA','Citas','cita',141,'2026-08-08 13:12:18','Cita agendada para 2026-08-08 15:00:00'),(409,12,'ATENCION','Citas','servicio_realizado',141,'2026-08-08 13:12:18','1 servicio(s), 1 producto(s) consumido(s)'),(410,12,'EMISION','Facturacion','factura',43,'2026-08-08 13:12:18','Comprobante 001-001-0000042 de la cita #141'),(411,12,'COBRO','Facturacion','factura',43,'2026-08-08 13:12:18','Cobro Gs. 25.000'),(412,12,'ALTA','Citas','cita',142,'2026-08-08 13:12:19','Cita agendada para 2026-08-08 15:00:00'),(413,12,'CANCELACION','Citas','cita',142,'2026-08-08 13:12:19','Cita cancelada'),(414,12,'ALTA','Citas','cita',143,'2026-08-08 13:12:19','Cita agendada para 2026-08-08 16:30:00'),(415,12,'ATENCION','Citas','servicio_realizado',143,'2026-08-08 13:12:19','1 servicio(s), 1 producto(s) consumido(s)'),(416,12,'EMISION','Facturacion','factura',44,'2026-08-08 13:12:19','Comprobante 001-001-0000043 de la cita #143'),(417,12,'COBRO','Facturacion','factura',44,'2026-08-08 13:12:19','Cobro Gs. 40.000 en 2 medios: Efectivo Gs. 24.000 + Tarjeta de debito Gs. 16.000'),(418,12,'ALTA','Citas','cita',144,'2026-08-08 13:12:20','Cita agendada para 2026-08-08 09:30:00'),(419,12,'ATENCION','Citas','servicio_realizado',144,'2026-08-08 13:12:20','2 servicio(s), 1 producto(s) consumido(s)'),(420,12,'EMISION','Facturacion','factura',45,'2026-08-08 13:12:20','Comprobante 001-001-0000044 de la cita #144'),(421,12,'COBRO','Facturacion','factura',45,'2026-08-08 13:12:20','Cobro Gs. 335.000 en 2 medios: Efectivo Gs. 201.000 + Tarjeta de debito Gs. 134.000'),(422,12,'AUSENCIA','Personal','asistencia',11,'2026-08-08 13:12:20','Sofía Espínola ausente el 2026-07-30 — con permiso: Motivo registrado'),(423,12,'ALTA','Citas','cita',145,'2026-08-08 13:12:20','Cita agendada para 2026-08-08 15:00:00'),(424,12,'ALTA','Citas','cita',146,'2026-08-08 13:12:21','Cita agendada para 2026-08-08 15:00:00'),(425,12,'ATENCION','Citas','servicio_realizado',146,'2026-08-08 13:12:21','2 servicio(s), 2 producto(s) consumido(s)'),(426,12,'EMISION','Facturacion','factura',46,'2026-08-08 13:12:21','Comprobante 001-001-0000045 de la cita #146'),(427,12,'COBRO','Facturacion','factura',46,'2026-08-08 13:12:21','Cobro Gs. 570.000'),(428,12,'ALTA','Citas','cita',147,'2026-08-08 13:12:21','Cita agendada para 2026-08-08 16:30:00'),(429,12,'ATENCION','Citas','servicio_realizado',147,'2026-08-08 13:12:21','2 servicio(s), 2 producto(s) consumido(s)'),(430,12,'EMISION','Facturacion','factura',47,'2026-08-08 13:12:22','Comprobante 001-001-0000046 de la cita #147'),(431,12,'COBRO','Facturacion','factura',47,'2026-08-08 13:12:22','Cobro Gs. 100.000 en 2 medios: Efectivo Gs. 60.000 + Tarjeta de debito Gs. 40.000'),(432,12,'ALTA','Citas','cita',148,'2026-08-08 13:12:22','Cita agendada para 2026-08-08 09:30:00'),(433,12,'ATENCION','Citas','servicio_realizado',148,'2026-08-08 13:12:22','1 servicio(s), 1 producto(s) consumido(s)'),(434,12,'EMISION','Facturacion','factura',48,'2026-08-08 13:12:22','Comprobante 001-001-0000047 de la cita #148'),(435,12,'COBRO','Facturacion','factura',48,'2026-08-08 13:12:22','Cobro Gs. 40.000'),(436,12,'ALTA','Citas','cita',149,'2026-08-08 13:12:23','Cita agendada para 2026-08-08 09:30:00'),(437,12,'ATENCION','Citas','servicio_realizado',149,'2026-08-08 13:12:23','1 servicio(s), 1 producto(s) consumido(s)'),(438,12,'EMISION','Facturacion','factura',49,'2026-08-08 13:12:23','Comprobante 001-001-0000048 de la cita #149'),(439,12,'COBRO','Facturacion','factura',49,'2026-08-08 13:12:23','Cobro Gs. 55.000'),(440,12,'ALTA','Citas','cita',150,'2026-08-08 13:12:23','Cita agendada para 2026-08-08 09:30:00'),(441,12,'ALTA','Citas','cita',151,'2026-08-08 13:12:24','Cita agendada para 2026-08-08 15:00:00'),(442,12,'ATENCION','Citas','servicio_realizado',151,'2026-08-08 13:12:24','2 servicio(s), 1 producto(s) consumido(s)'),(443,12,'EMISION','Facturacion','factura',50,'2026-08-08 13:12:24','Comprobante 001-001-0000049 de la cita #151'),(444,12,'COBRO','Facturacion','factura',50,'2026-08-08 13:12:24','Cobro Gs. 75.000 en 2 medios: Efectivo Gs. 45.000 + Tarjeta de debito Gs. 30.000'),(445,12,'ALTA','Citas','cita',152,'2026-08-08 13:12:25','Cita agendada para 2026-08-08 15:00:00'),(446,12,'ATENCION','Citas','servicio_realizado',152,'2026-08-08 13:12:25','1 servicio(s), 2 producto(s) consumido(s)'),(447,12,'EMISION','Facturacion','factura',51,'2026-08-08 13:12:25','Comprobante 001-001-0000050 de la cita #152'),(448,12,'COBRO','Facturacion','factura',51,'2026-08-08 13:12:25','Cobro Gs. 60.000'),(449,12,'ALTA','Citas','cita',153,'2026-08-08 13:12:25','Cita agendada para 2026-08-08 16:30:00'),(450,12,'ATENCION','Citas','servicio_realizado',153,'2026-08-08 13:12:25','1 servicio(s), 1 producto(s) consumido(s)'),(451,12,'EMISION','Facturacion','factura',52,'2026-08-08 13:12:26','Comprobante 001-001-0000051 de la cita #153'),(452,12,'COBRO','Facturacion','factura',52,'2026-08-08 13:12:26','Cobro Gs. 350.000'),(453,12,'ALTA','Citas','cita',154,'2026-08-08 13:12:26','Cita agendada para 2026-08-08 09:30:00'),(454,12,'ATENCION','Citas','servicio_realizado',154,'2026-08-08 13:12:26','1 servicio(s), 2 producto(s) consumido(s)'),(455,12,'EMISION','Facturacion','factura',53,'2026-08-08 13:12:26','Comprobante 001-001-0000052 de la cita #154'),(456,12,'COBRO','Facturacion','factura',53,'2026-08-08 13:12:26','Cobro Gs. 60.000'),(457,12,'ALTA','Citas','cita',155,'2026-08-08 13:12:27','Cita agendada para 2026-08-08 09:30:00'),(458,12,'ALTA','Citas','cita',156,'2026-08-08 13:12:27','Cita agendada para 2026-08-08 17:45:00'),(459,12,'ATENCION','Citas','servicio_realizado',156,'2026-08-08 13:12:27','1 servicio(s), 1 producto(s) consumido(s)'),(460,12,'EMISION','Facturacion','factura',54,'2026-08-08 13:12:27','Comprobante 001-001-0000053 de la cita #156'),(461,12,'COBRO','Facturacion','factura',54,'2026-08-08 13:12:27','Cobro Gs. 60.000'),(462,12,'ALTA','Citas','cita',157,'2026-08-08 13:12:28','Cita agendada para 2026-08-08 09:30:00'),(463,12,'ALTA','Citas','cita',158,'2026-08-08 13:12:28','Cita agendada para 2026-08-08 15:00:00'),(464,12,'CANCELACION','Citas','cita',158,'2026-08-08 13:12:28','Cita cancelada'),(465,12,'AUSENCIA','Personal','asistencia',11,'2026-08-08 13:12:29','Sofía Espínola ausente el 2026-08-04 — con permiso: Motivo registrado'),(466,12,'ALTA','Citas','cita',159,'2026-08-08 13:12:29','Cita agendada para 2026-08-08 15:00:00'),(467,12,'ALTA','Citas','cita',160,'2026-08-08 13:12:29','Cita agendada para 2026-08-08 15:00:00'),(468,12,'ATENCION','Citas','servicio_realizado',160,'2026-08-08 13:12:29','1 servicio(s), 1 producto(s) consumido(s)'),(469,12,'EMISION','Facturacion','factura',55,'2026-08-08 13:12:29','Comprobante 001-001-0000054 de la cita #160'),(470,12,'COBRO','Facturacion','factura',55,'2026-08-08 13:12:30','Cobro Gs. 280.000 en 2 medios: Efectivo Gs. 168.000 + Tarjeta de debito Gs. 112.000'),(471,12,'ALTA','Citas','cita',161,'2026-08-08 13:12:30','Cita agendada para 2026-08-08 09:30:00'),(472,12,'ATENCION','Citas','servicio_realizado',161,'2026-08-08 13:12:30','1 servicio(s), 2 producto(s) consumido(s)'),(473,12,'EMISION','Facturacion','factura',56,'2026-08-08 13:12:30','Comprobante 001-001-0000055 de la cita #161'),(474,12,'COBRO','Facturacion','factura',56,'2026-08-08 13:12:30','Cobro Gs. 40.000 en 2 medios: Efectivo Gs. 24.000 + Tarjeta de debito Gs. 16.000'),(475,12,'AUSENCIA','Personal','asistencia',11,'2026-08-08 13:12:31','Sofía Espínola ausente el 2026-08-05 — sin permiso: Motivo registrado'),(476,12,'ALTA','Citas','cita',162,'2026-08-08 13:12:31','Cita agendada para 2026-08-08 15:00:00'),(477,12,'ALTA','Citas','cita',163,'2026-08-08 13:12:31','Cita agendada para 2026-08-08 09:30:00'),(478,12,'ATENCION','Citas','servicio_realizado',163,'2026-08-08 13:12:31','1 servicio(s), 2 producto(s) consumido(s)'),(479,12,'EMISION','Facturacion','factura',57,'2026-08-08 13:12:31','Comprobante 001-001-0000056 de la cita #163'),(480,12,'COBRO','Facturacion','factura',57,'2026-08-08 13:12:32','Cobro Gs. 180.000'),(481,12,'ALTA','Citas','cita',164,'2026-08-08 13:12:32','Cita agendada para 2026-08-08 09:30:00'),(482,12,'ATENCION','Citas','servicio_realizado',164,'2026-08-08 13:12:32','1 servicio(s), 2 producto(s) consumido(s)'),(483,12,'EMISION','Facturacion','factura',58,'2026-08-08 13:12:32','Comprobante 001-001-0000057 de la cita #164'),(484,12,'COBRO','Facturacion','factura',58,'2026-08-08 13:12:32','Cobro Gs. 40.000'),(485,12,'ALTA','Citas','cita',165,'2026-08-08 13:12:33','Cita agendada para 2026-08-08 15:00:00'),(486,12,'ATENCION','Citas','servicio_realizado',165,'2026-08-08 13:12:33','2 servicio(s), 1 producto(s) consumido(s)'),(487,12,'EMISION','Facturacion','factura',59,'2026-08-08 13:12:33','Comprobante 001-001-0000058 de la cita #165'),(488,12,'COBRO','Facturacion','factura',59,'2026-08-08 13:12:33','Cobro Gs. 109.250'),(489,12,'ALTA','Citas','cita',166,'2026-08-08 13:12:33','Cita agendada para 2026-08-08 15:00:00'),(490,12,'CANCELACION','Citas','cita',166,'2026-08-08 13:12:33','Cita cancelada'),(491,12,'ALTA','Citas','cita',167,'2026-08-08 13:12:33','Cita agendada para 2026-08-08 11:00:00'),(492,12,'ALTA','Citas','cita',168,'2026-08-08 13:12:34','Cita agendada para 2026-08-08 15:00:00'),(493,12,'ALTA','Citas','cita',169,'2026-08-08 13:12:34','Cita agendada para 2026-08-08 09:30:00'),(494,12,'ATENCION','Citas','servicio_realizado',169,'2026-08-08 13:12:34','1 servicio(s), 1 producto(s) consumido(s)'),(495,12,'EMISION','Facturacion','factura',60,'2026-08-08 13:12:35','Comprobante 001-001-0000059 de la cita #169'),(496,12,'COBRO','Facturacion','factura',60,'2026-08-08 13:12:35','Cobro Gs. 50.000 en 2 medios: Efectivo Gs. 30.000 + Tarjeta de debito Gs. 20.000'),(497,12,'ALTA','Citas','cita',170,'2026-08-08 13:12:35','Cita agendada para 2026-08-08 09:30:00'),(498,12,'ALTA','Citas','cita',171,'2026-08-08 13:12:35','Cita agendada para 2026-08-08 11:00:00'),(499,12,'ATENCION','Citas','servicio_realizado',171,'2026-08-08 13:12:35','1 servicio(s), 1 producto(s) consumido(s)'),(500,12,'EMISION','Facturacion','factura',61,'2026-08-08 13:12:36','Comprobante 001-001-0000060 de la cita #171'),(501,12,'COBRO','Facturacion','factura',61,'2026-08-08 13:12:36','Cobro Gs. 180.000 en 2 medios: Efectivo Gs. 108.000 + Tarjeta de debito Gs. 72.000'),(502,1,'LOGIN','Seguridad','usuario',1,'2026-08-08 13:15:01','Inicio de sesión'),(503,1,'ALTA','Servicios','descuento',5,'2026-08-08 13:15:02','Promo Invierno vigente'),(504,1,'ALTA','Servicios','descuento',6,'2026-08-08 13:15:02','Promo vencida'),(505,1,'ALTA','Servicios','descuento',7,'2026-08-08 13:15:02','Promo futura'),(506,1,'SENA','Facturacion','cobro',71,'2026-08-08 13:15:02','Seña de Gs. 100.000 por la cita #12 (Andrea Villalba)'),(507,1,'ALTA','Citas','ausencia_agenda',1,'2026-08-08 13:15:02','Excepción 2026-08-18 a 2026-08-20'),(508,1,'LOGIN','Seguridad','usuario',1,'2026-08-08 13:17:45','Inicio de sesión'),(509,1,'MODIFICACION','Citas','cita',15,'2026-08-08 13:17:45','Reprogramada para 2026-08-12 17:00'),(510,1,'ALTA','Citas','ausencia_agenda',2,'2026-08-08 13:17:46','Excepción 2026-08-18 a 2026-08-20'),(511,1,'ANULAR','Cobros','cobro',69,'2026-08-08 13:17:46','Cobro anulado. Monto: 108000.00 Motivo: Error de cobro'),(512,1,'ANULAR','Cobros','cobro',70,'2026-08-08 13:17:46','Cobro anulado. Monto: 72000.00 Motivo: Error de cobro'),(513,1,'ANULAR','Facturacion','factura',61,'2026-08-08 13:17:46','Comprobante 001-001-0000060 anulado. Motivo: Servicio no prestado'),(514,1,'NOTA_CREDITO','Facturacion','factura',62,'2026-08-08 13:17:46','Nota de crédito 001-001-0000001 sobre 001-001-0000059 — Devolución del cliente'),(515,1,'ALTA','Personal','comision',3,'2026-08-08 13:17:47','Comisión PORCENTAJE 15'),(516,1,'PAGO_PERSONAL','Facturacion','pago_personal',2,'2026-08-08 13:17:47','Liquidación 2026-07 (20 servicios)'),(517,1,'LOGIN','Seguridad','usuario',1,'2026-08-08 13:18:43','Inicio de sesión'),(518,1,'REVERTIR','Pagos','pago_personal',2,'2026-08-08 13:18:43','Pago al personal del periodo 2026-07 Motivo: Gs. 169.500 a Lucía Benítez. Se liquidó de más'),(519,1,'ANULAR','Proveedores','pago_proveedor',2,'2026-08-08 13:18:43','Pago a proveedor anulado. Monto: 70000.00 Motivo: Pago duplicado'),(520,1,'CAJA_CIERRE','Facturacion','caja',3,'2026-08-08 13:18:44','Cierre con saldo Gs. 9.424.250'),(521,1,'CAJA_APERTURA','Facturacion','caja',4,'2026-08-08 13:18:44','Apertura con Gs. 300.000'),(522,1,'LOGIN','Seguridad','usuario',1,'2026-08-08 13:20:26','Inicio de sesión'),(523,1,'PAGO_PROVEEDOR','Facturacion','compra',4,'2026-08-08 13:20:27','Pago Gs. 1.584.000'),(524,1,'ANULAR','Proveedores','pago_proveedor',3,'2026-08-08 13:20:27','Pago a proveedor anulado. Monto: 1584000.00 Motivo: Pago duplicado'),(525,1,'LOGIN','Seguridad','usuario',1,'2026-08-08 13:23:07','Inicio de sesión'),(526,2,'LOGIN','Seguridad','usuario',2,'2026-08-08 13:23:07','Inicio de sesión'),(527,8,'LOGIN','Seguridad','usuario',8,'2026-08-08 13:23:09','Inicio de sesión'),(528,8,'MODIFICACION','Servicios','servicio',3,'2026-08-08 13:23:10','Corte de dama'),(529,1,'LOGIN','Seguridad','usuario',1,'2026-08-08 13:23:11','Inicio de sesión'),(530,1,'MODIFICACION','Servicios','servicio',3,'2026-08-08 13:23:11','Corte de dama'),(531,8,'LOGIN','Seguridad','usuario',8,'2026-08-08 13:23:11','Inicio de sesión'),(532,1,'LOGIN','Seguridad','usuario',1,'2026-08-08 13:23:12','Inicio de sesión'),(533,1,'ALTA','Clientes','cliente',35,'2026-08-08 13:23:12','AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'),(534,1,'ALTA','Clientes','cliente',36,'2026-08-08 13:23:12','Test Test'),(535,1,'ALTA','Clientes','cliente',37,'2026-08-08 13:23:12','<script>alert(1)</script> XSS'),(536,12,'LOGIN','Seguridad','usuario',12,'2026-08-08 13:23:13','Inicio de sesión'),(537,1,'LOGIN','Seguridad','usuario',1,'2026-08-08 13:23:14','Inicio de sesión'),(538,1,'EMISION','Facturacion','factura',63,'2026-08-08 13:23:14','Comprobante 001-001-0000061 de la cita #171'),(539,2,'LOGIN','Seguridad','usuario',2,'2026-08-08 13:25:01','Inicio de sesión'),(540,1,'LOGIN','Seguridad','usuario',1,'2026-08-08 13:25:54','Inicio de sesión'),(541,1,'ALTA','Citas','cita',173,'2026-08-08 13:25:54','Cita agendada para 2026-08-08 08:30:00'),(542,1,'ATENCION','Citas','servicio_realizado',173,'2026-08-08 13:25:54','1 servicio(s), 0 producto(s) consumido(s)'),(543,2,'LOGIN','Seguridad','usuario',2,'2026-08-08 13:25:55','Inicio de sesión'),(544,13,'LOGIN','Seguridad','usuario',13,'2026-08-08 13:27:17','Inicio de sesión'),(545,13,'LOGIN','Seguridad','usuario',13,'2026-08-08 13:29:11','Inicio de sesión'),(546,1,'LOGIN','Seguridad','usuario',1,'2026-08-08 16:59:56','Inicio de sesión'),(547,1,'ALTA','Citas','cita',174,'2026-08-08 16:59:58','Cita agendada para 2026-08-29 14:00:00'),(548,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:35:52','Inicio de sesión'),(549,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:40:16','Inicio de sesión'),(550,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:40:19','Inicio de sesión'),(551,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:40:19','Inicio de sesión'),(552,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:40:22','Inicio de sesión'),(553,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:40:23','Inicio de sesión'),(554,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:43:11','Inicio de sesión'),(555,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:43:12','Inicio de sesión'),(556,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:43:12','Inicio de sesión'),(557,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:43:13','Inicio de sesión'),(558,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:43:14','Inicio de sesión'),(559,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:44:12','Inicio de sesión'),(560,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:44:13','Inicio de sesión'),(561,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:44:14','Inicio de sesión'),(562,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:44:15','Inicio de sesión'),(563,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:44:17','Inicio de sesión'),(564,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:45:24','Inicio de sesión'),(565,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:45:24','Inicio de sesión'),(566,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:45:24','Inicio de sesión'),(567,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:45:27','Inicio de sesión'),(568,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:45:27','Inicio de sesión'),(569,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 19:47:53','Inicio de sesión'),(570,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 20:06:22','Inicio de sesión'),(571,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 20:06:23','Inicio de sesión'),(572,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 20:06:23','Inicio de sesión'),(573,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 20:06:25','Inicio de sesión'),(574,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 20:06:25','Inicio de sesión'),(575,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 20:18:01','Inicio de sesión'),(576,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 20:18:01','Inicio de sesión'),(577,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 20:18:02','Inicio de sesión'),(578,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 20:18:08','Inicio de sesión'),(579,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 20:18:08','Inicio de sesión'),(580,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 20:20:44','Inicio de sesión'),(581,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 20:20:45','Inicio de sesión'),(582,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 20:20:45','Inicio de sesión'),(583,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 20:20:48','Inicio de sesión'),(584,1,'LOGIN','Seguridad','usuario',1,'2026-08-09 20:20:49','Inicio de sesión'),(585,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:11:06','Inicio de sesión'),(586,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:11:07','Inicio de sesión'),(587,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:11:07','Inicio de sesión'),(588,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:11:12','Inicio de sesión'),(589,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:11:12','Inicio de sesión'),(590,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:12:00','Inicio de sesión'),(591,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:33:01','Inicio de sesión'),(592,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:33:02','Inicio de sesión'),(593,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:33:02','Inicio de sesión'),(594,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:33:07','Inicio de sesión'),(595,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:33:07','Inicio de sesión'),(596,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:42:26','Inicio de sesión'),(597,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:42:26','Inicio de sesión'),(598,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:42:27','Inicio de sesión'),(599,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:42:32','Inicio de sesión'),(600,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:42:33','Inicio de sesión'),(601,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:46:46','Inicio de sesión'),(602,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:46:47','Inicio de sesión'),(603,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:46:48','Inicio de sesión'),(604,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:46:53','Inicio de sesión'),(605,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 11:46:54','Inicio de sesión'),(606,2,'LOGIN','Seguridad','usuario',2,'2026-08-10 11:47:15','Inicio de sesión'),(607,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:26:14','Inicio de sesión'),(608,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:26:14','Inicio de sesión'),(609,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:26:15','Inicio de sesión'),(610,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:26:22','Inicio de sesión'),(611,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:26:22','Inicio de sesión'),(613,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:39:38','Inicio de sesión'),(614,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:39:39','Inicio de sesión'),(615,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:39:39','Inicio de sesión'),(616,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:39:42','Inicio de sesión'),(617,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:39:42','Inicio de sesión'),(618,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:40:11','Inicio de sesión'),(619,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:40:55','Inicio de sesión'),(620,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:52:10','Inicio de sesión'),(621,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:52:11','Inicio de sesión'),(622,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:52:11','Inicio de sesión'),(623,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:52:15','Inicio de sesión'),(624,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:52:16','Inicio de sesión'),(625,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:58:11','Inicio de sesión'),(626,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:58:11','Inicio de sesión'),(627,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:58:11','Inicio de sesión'),(628,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:58:13','Inicio de sesión'),(629,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 16:58:13','Inicio de sesión'),(630,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 17:08:27','Inicio de sesión'),(631,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 17:51:06','Inicio de sesión'),(632,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 17:51:07','Inicio de sesión'),(633,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 17:51:07','Inicio de sesión'),(634,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 17:51:13','Inicio de sesión'),(635,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 17:51:14','Inicio de sesión'),(636,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 18:03:05','Inicio de sesión'),(637,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 18:03:05','Inicio de sesión'),(638,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 18:03:05','Inicio de sesión'),(639,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 18:03:07','Inicio de sesión'),(640,1,'LOGIN','Seguridad','usuario',1,'2026-08-10 18:03:07','Inicio de sesión'),(641,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:43:17','Inicio de sesión'),(642,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:43:19','Inicio de sesión'),(643,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:43:20','Inicio de sesión'),(644,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:43:24','Inicio de sesión'),(645,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:43:25','Inicio de sesión'),(646,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:44:26','Inicio de sesión'),(647,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:44:27','Inicio de sesión'),(648,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:44:28','Inicio de sesión'),(649,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:44:32','Inicio de sesión'),(650,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:44:33','Inicio de sesión'),(651,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:44:42','Inicio de sesión'),(652,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:45:45','Inicio de sesión'),(653,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:45:46','Inicio de sesión'),(654,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:45:46','Inicio de sesión'),(655,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:45:50','Inicio de sesión'),(656,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:45:51','Inicio de sesión'),(657,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:45:52','Inicio de sesión'),(658,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:47:39','Inicio de sesión'),(659,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:47:40','Inicio de sesión'),(660,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:47:40','Inicio de sesión'),(661,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:47:45','Inicio de sesión'),(662,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:47:46','Inicio de sesión'),(663,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:47:47','Inicio de sesión'),(664,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:50:34','Inicio de sesión'),(665,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:50:35','Inicio de sesión'),(666,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:50:35','Inicio de sesión'),(667,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:50:39','Inicio de sesión'),(668,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:50:39','Inicio de sesión'),(669,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:50:41','Inicio de sesión'),(670,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:51:42','Inicio de sesión'),(671,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 08:58:44','Inicio de sesión'),(672,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:18:50','Inicio de sesión'),(673,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:18:51','Inicio de sesión'),(674,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:18:52','Inicio de sesión'),(675,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:18:57','Inicio de sesión'),(676,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:18:58','Inicio de sesión'),(677,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:19:00','Inicio de sesión'),(678,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:31:50','Inicio de sesión'),(679,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:31:51','Inicio de sesión'),(680,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:31:52','Inicio de sesión'),(681,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:31:55','Inicio de sesión'),(682,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:31:55','Inicio de sesión'),(683,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:31:57','Inicio de sesión'),(684,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:32:00','Inicio de sesión'),(685,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:32:00','Inicio de sesión'),(686,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:32:21','Inicio de sesión'),(687,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:33:02','Inicio de sesión'),(688,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:33:02','Inicio de sesión'),(689,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:33:02','Inicio de sesión'),(690,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:33:05','Inicio de sesión'),(691,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:33:06','Inicio de sesión'),(692,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:33:07','Inicio de sesión'),(693,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:33:10','Inicio de sesión'),(694,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:33:10','Inicio de sesión'),(704,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:41:55','Inicio de sesión'),(705,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:43:39','Inicio de sesión'),(706,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:53:36','Inicio de sesión'),(707,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:53:38','Inicio de sesión'),(708,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:53:38','Inicio de sesión'),(709,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:53:43','Inicio de sesión'),(710,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:53:43','Inicio de sesión'),(711,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:53:45','Inicio de sesión'),(712,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:53:48','Inicio de sesión'),(713,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 09:53:49','Inicio de sesión'),(719,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:00:00','Inicio de sesión'),(720,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:00:01','Inicio de sesión'),(721,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:00:01','Inicio de sesión'),(722,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:00:05','Inicio de sesión'),(723,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:00:06','Inicio de sesión'),(724,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:00:06','Inicio de sesión'),(725,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:00:07','Inicio de sesión'),(726,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:00:08','Inicio de sesión'),(732,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:01:32','Inicio de sesión'),(735,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:05:33','Inicio de sesión'),(736,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:05:34','Inicio de sesión'),(737,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:05:34','Inicio de sesión'),(738,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:05:38','Inicio de sesión'),(739,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:05:39','Inicio de sesión'),(740,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:05:40','Inicio de sesión'),(741,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:05:44','Inicio de sesión'),(742,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 10:05:45','Inicio de sesión'),(748,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 14:33:11','Inicio de sesión'),(749,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 14:33:13','Inicio de sesión'),(750,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 14:33:13','Inicio de sesión'),(751,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 14:33:18','Inicio de sesión'),(752,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 14:33:19','Inicio de sesión'),(753,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 14:33:20','Inicio de sesión'),(754,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 14:33:23','Inicio de sesión'),(755,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 14:33:24','Inicio de sesión'),(761,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:31:47','Inicio de sesión'),(762,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:31:48','Inicio de sesión'),(763,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:31:49','Inicio de sesión'),(764,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:31:54','Inicio de sesión'),(765,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:31:55','Inicio de sesión'),(766,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:31:57','Inicio de sesión'),(767,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:32:01','Inicio de sesión'),(768,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:32:02','Inicio de sesión'),(774,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:33:19','Inicio de sesión'),(775,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:33:20','Inicio de sesión'),(776,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:33:21','Inicio de sesión'),(777,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:33:25','Inicio de sesión'),(778,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:33:26','Inicio de sesión'),(779,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:33:27','Inicio de sesión'),(780,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:33:32','Inicio de sesión'),(781,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:33:32','Inicio de sesión'),(787,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:34:34','Inicio de sesión'),(788,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:34:35','Inicio de sesión'),(789,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:34:36','Inicio de sesión'),(790,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:34:41','Inicio de sesión'),(791,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:34:42','Inicio de sesión'),(792,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:34:43','Inicio de sesión'),(793,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:34:46','Inicio de sesión'),(794,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:34:47','Inicio de sesión'),(800,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:35:48','Inicio de sesión'),(801,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:35:49','Inicio de sesión'),(802,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:35:49','Inicio de sesión'),(803,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:35:51','Inicio de sesión'),(804,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:35:51','Inicio de sesión'),(805,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:35:52','Inicio de sesión'),(806,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:35:53','Inicio de sesión'),(807,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:35:53','Inicio de sesión'),(813,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:38:53','Inicio de sesión'),(814,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:38:54','Inicio de sesión'),(815,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:38:55','Inicio de sesión'),(816,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:38:59','Inicio de sesión'),(817,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:39:00','Inicio de sesión'),(818,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:39:01','Inicio de sesión'),(819,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:39:05','Inicio de sesión'),(820,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 15:39:05','Inicio de sesión'),(826,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 16:00:28','Inicio de sesión'),(827,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 16:00:28','Inicio de sesión'),(828,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 16:00:29','Inicio de sesión'),(829,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 16:00:34','Inicio de sesión'),(830,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 16:00:35','Inicio de sesión'),(831,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 16:00:36','Inicio de sesión'),(832,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 16:00:38','Inicio de sesión'),(833,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 16:00:38','Inicio de sesión'),(842,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:35:13','Inicio de sesión'),(843,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:35:14','Inicio de sesión'),(844,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:35:15','Inicio de sesión'),(845,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:35:22','Inicio de sesión'),(846,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:35:22','Inicio de sesión'),(847,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:35:24','Inicio de sesión'),(848,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:35:28','Inicio de sesión'),(849,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:35:29','Inicio de sesión'),(855,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:38:42','Inicio de sesión'),(856,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:38:43','Inicio de sesión'),(857,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:38:44','Inicio de sesión'),(858,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:38:50','Inicio de sesión'),(859,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:38:50','Inicio de sesión'),(860,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:38:52','Inicio de sesión'),(861,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:38:56','Inicio de sesión'),(862,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:38:56','Inicio de sesión'),(868,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:51:13','Inicio de sesión'),(869,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:51:13','Inicio de sesión'),(870,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:51:13','Inicio de sesión'),(871,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:51:18','Inicio de sesión'),(872,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:51:20','Inicio de sesión'),(873,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:51:21','Inicio de sesión'),(874,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:51:24','Inicio de sesión'),(875,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 18:51:24','Inicio de sesión'),(881,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 19:23:04','Inicio de sesión'),(882,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 19:23:04','Inicio de sesión'),(883,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 19:23:05','Inicio de sesión'),(884,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 19:23:12','Inicio de sesión'),(885,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 19:23:12','Inicio de sesión'),(886,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 19:23:13','Inicio de sesión'),(887,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 19:23:15','Inicio de sesión'),(888,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 19:23:15','Inicio de sesión'),(894,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 19:25:01','Inicio de sesión'),(895,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 19:25:01','Inicio de sesión'),(896,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 19:25:01','Inicio de sesión'),(897,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 19:25:03','Inicio de sesión'),(898,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 19:25:04','Inicio de sesión'),(899,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 19:25:04','Inicio de sesión'),(900,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 19:25:06','Inicio de sesión'),(901,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 19:25:06','Inicio de sesión'),(907,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 20:00:59','Inicio de sesión'),(908,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 20:00:59','Inicio de sesión'),(909,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 20:01:00','Inicio de sesión'),(910,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 20:01:04','Inicio de sesión'),(911,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 20:01:04','Inicio de sesión'),(912,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 20:01:06','Inicio de sesión'),(913,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 20:01:08','Inicio de sesión'),(914,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 20:01:08','Inicio de sesión'),(920,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 20:04:16','Inicio de sesión'),(921,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 20:04:16','Inicio de sesión'),(922,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 20:04:16','Inicio de sesión'),(923,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 20:04:18','Inicio de sesión'),(924,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 20:04:18','Inicio de sesión'),(925,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 20:04:19','Inicio de sesión'),(926,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 20:04:20','Inicio de sesión'),(927,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 20:04:20','Inicio de sesión'),(933,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 21:32:17','Inicio de sesión'),(934,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 21:32:18','Inicio de sesión'),(935,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 21:32:18','Inicio de sesión'),(936,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 21:32:24','Inicio de sesión'),(937,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 21:32:24','Inicio de sesión'),(938,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 21:32:25','Inicio de sesión'),(939,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 22:53:43','Inicio de sesión'),(940,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 22:53:43','Inicio de sesión'),(941,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 22:53:44','Inicio de sesión'),(942,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 22:53:48','Inicio de sesión'),(943,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 22:53:49','Inicio de sesión'),(944,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 22:53:50','Inicio de sesión'),(945,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 22:53:53','Inicio de sesión'),(946,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 22:53:53','Inicio de sesión'),(952,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:03:39','Inicio de sesión'),(953,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:03:39','Inicio de sesión'),(954,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:03:40','Inicio de sesión'),(955,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:03:44','Inicio de sesión'),(956,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:03:45','Inicio de sesión'),(957,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:03:46','Inicio de sesión'),(958,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:03:50','Inicio de sesión'),(959,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:03:51','Inicio de sesión'),(965,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:15:20','Inicio de sesión'),(966,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:15:21','Inicio de sesión'),(967,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:15:22','Inicio de sesión'),(968,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:15:27','Inicio de sesión'),(969,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:15:28','Inicio de sesión'),(970,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:15:29','Inicio de sesión'),(971,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:15:32','Inicio de sesión'),(972,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:15:33','Inicio de sesión'),(980,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:18:27','Inicio de sesión'),(981,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:18:28','Inicio de sesión'),(982,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:18:29','Inicio de sesión'),(983,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:18:33','Inicio de sesión'),(984,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:18:34','Inicio de sesión'),(985,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:18:35','Inicio de sesión'),(986,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:18:38','Inicio de sesión'),(987,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:18:38','Inicio de sesión'),(995,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:21:57','Inicio de sesión'),(996,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:21:57','Inicio de sesión'),(997,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:21:58','Inicio de sesión'),(998,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:22:02','Inicio de sesión'),(999,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:22:03','Inicio de sesión'),(1000,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:22:04','Inicio de sesión'),(1001,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:22:09','Inicio de sesión'),(1002,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:22:09','Inicio de sesión'),(1010,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:36:33','Inicio de sesión'),(1011,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:36:34','Inicio de sesión'),(1012,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:36:35','Inicio de sesión'),(1013,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:36:39','Inicio de sesión'),(1014,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:36:40','Inicio de sesión'),(1015,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:36:41','Inicio de sesión'),(1016,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:36:45','Inicio de sesión'),(1017,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:36:46','Inicio de sesión'),(1025,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:48:54','Inicio de sesión'),(1026,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:48:55','Inicio de sesión'),(1027,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:48:56','Inicio de sesión'),(1028,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:48:59','Inicio de sesión'),(1029,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:49:00','Inicio de sesión'),(1030,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:49:01','Inicio de sesión'),(1031,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:49:05','Inicio de sesión'),(1032,1,'LOGIN','Seguridad','usuario',1,'2026-08-11 23:49:06','Inicio de sesión'),(1040,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 00:21:19','Inicio de sesión'),(1041,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 00:21:20','Inicio de sesión'),(1042,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 00:21:20','Inicio de sesión'),(1043,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 00:21:25','Inicio de sesión'),(1044,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 00:21:26','Inicio de sesión'),(1045,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 00:21:27','Inicio de sesión'),(1046,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 00:21:31','Inicio de sesión'),(1047,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 00:21:31','Inicio de sesión'),(1055,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 00:23:22','Inicio de sesión'),(1056,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 00:23:23','Inicio de sesión'),(1057,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 00:23:23','Inicio de sesión'),(1058,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 00:23:28','Inicio de sesión'),(1059,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 00:23:29','Inicio de sesión'),(1060,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 00:23:30','Inicio de sesión'),(1061,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 00:23:34','Inicio de sesión'),(1062,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 00:23:35','Inicio de sesión'),(1070,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:24:14','Inicio de sesión'),(1071,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:24:15','Inicio de sesión'),(1072,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:24:15','Inicio de sesión'),(1073,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:24:21','Inicio de sesión'),(1074,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:24:22','Inicio de sesión'),(1075,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:24:24','Inicio de sesión'),(1076,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:24:27','Inicio de sesión'),(1077,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:24:28','Inicio de sesión'),(1085,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:25:08','Inicio de sesión'),(1086,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:25:09','Inicio de sesión'),(1087,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:25:10','Inicio de sesión'),(1088,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:25:13','Inicio de sesión'),(1089,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:25:14','Inicio de sesión'),(1090,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:25:15','Inicio de sesión'),(1091,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:25:19','Inicio de sesión'),(1092,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:25:20','Inicio de sesión'),(1100,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:30:44','Inicio de sesión'),(1101,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:30:45','Inicio de sesión'),(1102,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:30:45','Inicio de sesión'),(1103,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:30:52','Inicio de sesión'),(1104,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:30:53','Inicio de sesión'),(1105,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:30:55','Inicio de sesión'),(1106,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:30:57','Inicio de sesión'),(1107,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 18:30:57','Inicio de sesión'),(1115,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 19:13:56','Inicio de sesión'),(1116,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 19:13:57','Inicio de sesión'),(1117,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 19:13:58','Inicio de sesión'),(1118,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 19:14:04','Inicio de sesión'),(1119,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 19:14:05','Inicio de sesión'),(1120,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 19:14:07','Inicio de sesión'),(1121,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 19:14:11','Inicio de sesión'),(1122,1,'LOGIN','Seguridad','usuario',1,'2026-08-12 19:14:12','Inicio de sesión'),(1130,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 08:52:20','Inicio de sesión'),(1131,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 08:52:22','Inicio de sesión'),(1132,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 08:52:23','Inicio de sesión'),(1133,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 08:52:28','Inicio de sesión'),(1134,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 08:52:29','Inicio de sesión'),(1135,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 08:52:30','Inicio de sesión'),(1136,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 08:52:35','Inicio de sesión'),(1137,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 08:52:36','Inicio de sesión'),(1145,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 08:58:14','Inicio de sesión'),(1146,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 08:58:15','Inicio de sesión'),(1147,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 08:58:15','Inicio de sesión'),(1148,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 08:58:19','Inicio de sesión'),(1149,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 08:58:19','Inicio de sesión'),(1150,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 08:58:20','Inicio de sesión'),(1151,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 08:58:25','Inicio de sesión'),(1152,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 08:58:25','Inicio de sesión'),(1160,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:31:21','Inicio de sesión'),(1161,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:31:22','Inicio de sesión'),(1162,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:31:22','Inicio de sesión'),(1163,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:31:27','Inicio de sesión'),(1164,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:31:28','Inicio de sesión'),(1165,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:31:29','Inicio de sesión'),(1166,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:31:33','Inicio de sesión'),(1167,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:31:33','Inicio de sesión'),(1175,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:32:32','Inicio de sesión'),(1176,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:32:52','Inicio de sesión'),(1177,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:33:39','Inicio de sesión'),(1178,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:33:39','Inicio de sesión'),(1179,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:33:40','Inicio de sesión'),(1180,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:33:45','Inicio de sesión'),(1181,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:33:46','Inicio de sesión'),(1182,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:33:47','Inicio de sesión'),(1183,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:33:48','Inicio de sesión'),(1184,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:33:52','Inicio de sesión'),(1185,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:33:53','Inicio de sesión'),(1193,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:36:31','Inicio de sesión'),(1194,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:36:32','Inicio de sesión'),(1195,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:36:33','Inicio de sesión'),(1196,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:36:38','Inicio de sesión'),(1197,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:36:39','Inicio de sesión'),(1198,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:36:40','Inicio de sesión'),(1199,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:36:41','Inicio de sesión'),(1200,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:36:44','Inicio de sesión'),(1201,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 09:36:45','Inicio de sesión'),(1209,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 10:08:06','Inicio de sesión'),(1210,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 10:08:07','Inicio de sesión'),(1211,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 10:08:07','Inicio de sesión'),(1212,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 10:08:12','Inicio de sesión'),(1213,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 10:08:12','Inicio de sesión'),(1214,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 10:08:14','Inicio de sesión'),(1215,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 10:08:15','Inicio de sesión'),(1216,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 10:08:20','Inicio de sesión'),(1217,1,'LOGIN','Seguridad','usuario',1,'2026-08-13 10:08:20','Inicio de sesión'),(1353,1,'LOGIN','Seguridad','usuario',1,'2026-08-14 15:25:11','Inicio de sesión'),(1354,1,'LOGIN','Seguridad','usuario',1,'2026-08-14 15:25:11','Inicio de sesión cerrando la sesión abierta en otro equipo'),(2126,1,'LOGIN','Seguridad','usuario',1,'2026-08-17 09:14:05','Inicio de sesión');
/*!40000 ALTER TABLE `auditoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ausencia_agenda`
--

DROP TABLE IF EXISTS `ausencia_agenda`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ausencia_agenda` (
  `id_ausencia` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned DEFAULT NULL,
  `id_tipo_ausencia` int(10) unsigned NOT NULL,
  `fecha_inicio` datetime NOT NULL,
  `fecha_fin` datetime NOT NULL,
  `motivo` varchar(200) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_ausencia`),
  KEY `idx_ausencia_usuario` (`id_usuario`,`fecha_inicio`),
  KEY `idx_ausencia_tipo` (`id_tipo_ausencia`),
  CONSTRAINT `fk_ausencia_tipo` FOREIGN KEY (`id_tipo_ausencia`) REFERENCES `tipo_ausencia` (`id_tipo_ausencia`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ausencia_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_ausencia_rango` CHECK (`fecha_fin` > `fecha_inicio`)
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ausencia_agenda`
--

LOCK TABLES `ausencia_agenda` WRITE;
/*!40000 ALTER TABLE `ausencia_agenda` DISABLE KEYS */;
INSERT INTO `ausencia_agenda` VALUES (1,9,1,'2026-08-18 00:00:00','2026-08-20 00:00:00','Vacaciones',1),(2,9,1,'2026-08-18 00:00:00','2026-08-20 00:00:00','Vacaciones',1);
/*!40000 ALTER TABLE `ausencia_agenda` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `caja`
--

DROP TABLE IF EXISTS `caja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `caja` (
  `id_caja` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  `id_estado_caja` int(10) unsigned NOT NULL,
  `fecha_apertura` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_cierre` datetime DEFAULT NULL,
  `monto_inicial` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_caja`),
  KEY `idx_caja_usuario` (`id_usuario`),
  KEY `idx_caja_estado` (`id_estado_caja`),
  KEY `fk_caja_sucursal` (`id_sucursal`),
  CONSTRAINT `fk_caja_estado` FOREIGN KEY (`id_estado_caja`) REFERENCES `estado_caja` (`id_estado_caja`) ON UPDATE CASCADE,
  CONSTRAINT `fk_caja_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursal` (`id_sucursal`),
  CONSTRAINT `fk_caja_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `chk_caja_inicial` CHECK (`monto_inicial` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `caja`
--

LOCK TABLES `caja` WRITE;
/*!40000 ALTER TABLE `caja` DISABLE KEYS */;
INSERT INTO `caja` VALUES (2,1,1,2,'2026-08-04 22:18:15','2026-08-04 22:22:32',100000.00),(3,1,1,2,'2026-08-08 13:08:53','2026-08-08 13:18:44',500000.00),(4,1,1,1,'2026-08-08 13:18:44',NULL,300000.00);
/*!40000 ALTER TABLE `caja` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_caja_bi BEFORE INSERT ON caja
FOR EACH ROW
BEGIN
  IF NEW.id_estado_caja = 1
     AND EXISTS (SELECT 1 FROM caja WHERE id_estado_caja = 1) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Ya hay una caja abierta en el salon. Cerrala antes de abrir otra.';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `calificacion`
--

DROP TABLE IF EXISTS `calificacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `calificacion` (
  `id_calificacion` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cita` int(10) unsigned NOT NULL,
  `puntaje` tinyint(3) unsigned NOT NULL,
  `comentario` varchar(300) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_calificacion`),
  UNIQUE KEY `uq_calificacion_cita` (`id_cita`),
  CONSTRAINT `fk_calificacion_cita` FOREIGN KEY (`id_cita`) REFERENCES `cita` (`id_cita`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_calificacion_puntaje` CHECK (`puntaje` between 1 and 5)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calificacion`
--

LOCK TABLES `calificacion` WRITE;
/*!40000 ALTER TABLE `calificacion` DISABLE KEYS */;
INSERT INTO `calificacion` VALUES (1,171,5,'Excelente atención','2026-08-08 13:25:02'),(2,173,5,'Excelente atención','2026-08-08 13:25:55');
/*!40000 ALTER TABLE `calificacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `canje`
--

DROP TABLE IF EXISTS `canje`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `canje` (
  `id_canje` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `id_servicio` int(10) unsigned NOT NULL,
  `puntos` int(11) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `vence_en` date NOT NULL,
  `id_cita` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_canje`),
  KEY `idx_canje_cliente` (`id_cliente`),
  KEY `idx_canje_cita` (`id_cita`),
  KEY `fk_canje_servicio` (`id_servicio`),
  CONSTRAINT `fk_canje_cita` FOREIGN KEY (`id_cita`) REFERENCES `cita` (`id_cita`),
  CONSTRAINT `fk_canje_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_canje_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicio` (`id_servicio`),
  CONSTRAINT `chk_canje_puntos` CHECK (`puntos` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=133 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `canje`
--

LOCK TABLES `canje` WRITE;
/*!40000 ALTER TABLE `canje` DISABLE KEYS */;
/*!40000 ALTER TABLE `canje` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `canjeable_sucursal`
--

DROP TABLE IF EXISTS `canjeable_sucursal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `canjeable_sucursal` (
  `id_servicio_canjeable` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_servicio_canjeable`,`id_sucursal`),
  KEY `ix_canjsuc_sucursal` (`id_sucursal`),
  CONSTRAINT `fk_canjsuc_canjeable` FOREIGN KEY (`id_servicio_canjeable`) REFERENCES `servicio_canjeable` (`id_servicio_canjeable`),
  CONSTRAINT `fk_canjsuc_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursal` (`id_sucursal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `canjeable_sucursal`
--

LOCK TABLES `canjeable_sucursal` WRITE;
/*!40000 ALTER TABLE `canjeable_sucursal` DISABLE KEYS */;
INSERT INTO `canjeable_sucursal` VALUES (1,1),(2,1);
/*!40000 ALTER TABLE `canjeable_sucursal` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categoria_producto`
--

DROP TABLE IF EXISTS `categoria_producto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categoria_producto` (
  `id_categoria` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  PRIMARY KEY (`id_categoria`),
  UNIQUE KEY `uq_categoria_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categoria_producto`
--

LOCK TABLES `categoria_producto` WRITE;
/*!40000 ALTER TABLE `categoria_producto` DISABLE KEYS */;
INSERT INTO `categoria_producto` VALUES (2,'Cuidado capilar'),(4,'Herramientas y accesorios'),(3,'Insumos descartables'),(5,'Productos de reventa'),(1,'Tinturas y coloracion');
/*!40000 ALTER TABLE `categoria_producto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categoria_servicio`
--

DROP TABLE IF EXISTS `categoria_servicio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categoria_servicio` (
  `id_categoria_servicio` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  PRIMARY KEY (`id_categoria_servicio`),
  UNIQUE KEY `uq_categoria_servicio_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categoria_servicio`
--

LOCK TABLES `categoria_servicio` WRITE;
/*!40000 ALTER TABLE `categoria_servicio` DISABLE KEYS */;
INSERT INTO `categoria_servicio` VALUES (2,'Coloracion'),(1,'Corte'),(5,'Manicura y pedicura'),(6,'Otros'),(4,'Peinado y brushing'),(3,'Tratamiento capilar');
/*!40000 ALTER TABLE `categoria_servicio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cita`
--

DROP TABLE IF EXISTS `cita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cita` (
  `id_cita` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  `id_estado_cita` int(10) unsigned NOT NULL,
  `fecha_hora` datetime NOT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `observaciones` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`id_cita`),
  KEY `idx_cita_cliente` (`id_cliente`),
  KEY `idx_cita_usuario` (`id_usuario`,`fecha_hora`),
  KEY `idx_cita_estado` (`id_estado_cita`),
  KEY `idx_cita_fecha` (`fecha_hora`),
  KEY `fk_cita_sucursal` (`id_sucursal`),
  CONSTRAINT `fk_cita_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`) ON UPDATE CASCADE,
  CONSTRAINT `fk_cita_estado` FOREIGN KEY (`id_estado_cita`) REFERENCES `estado_cita` (`id_estado_cita`) ON UPDATE CASCADE,
  CONSTRAINT `fk_cita_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursal` (`id_sucursal`),
  CONSTRAINT `fk_cita_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=706 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cita`
--

LOCK TABLES `cita` WRITE;
/*!40000 ALTER TABLE `cita` DISABLE KEYS */;
INSERT INTO `cita` VALUES (3,9,11,1,4,'2026-08-08 13:30:00','2026-08-08 13:05:25',NULL),(4,7,10,1,4,'2026-08-08 08:00:00','2026-08-08 13:05:25',NULL),(5,27,9,1,4,'2026-08-08 13:30:00','2026-08-08 13:05:25',NULL),(6,8,8,1,4,'2026-08-08 08:00:00','2026-08-08 13:05:25',NULL),(7,5,9,1,4,'2026-08-08 17:20:00','2026-08-08 13:05:25',NULL),(8,9,8,1,4,'2026-08-08 09:15:00','2026-08-08 13:05:25',NULL),(9,7,10,1,4,'2026-08-08 09:25:00','2026-08-08 13:05:25',NULL),(10,5,8,1,4,'2026-08-08 11:10:00','2026-08-08 13:05:26',NULL),(11,10,9,1,7,'2026-08-10 13:30:00','2026-08-08 13:05:26',NULL),(12,7,8,1,7,'2026-08-10 08:00:00','2026-08-08 13:05:26',NULL),(13,7,11,1,7,'2026-08-10 13:30:00','2026-08-08 13:05:26',NULL),(14,12,11,1,7,'2026-08-11 13:30:00','2026-08-08 13:05:26',NULL),(15,18,9,1,7,'2026-08-12 17:00:00','2026-08-08 13:05:26',NULL),(16,8,9,1,7,'2026-08-12 13:30:00','2026-08-08 13:05:26',NULL),(17,5,11,1,7,'2026-08-12 13:30:00','2026-08-08 13:05:26',NULL),(18,14,11,1,7,'2026-08-12 15:25:00','2026-08-08 13:05:27',NULL),(19,8,11,1,7,'2026-08-12 16:15:00','2026-08-08 13:05:27',NULL),(20,1,8,1,7,'2026-08-13 08:00:00','2026-08-08 13:05:27',NULL),(21,1,8,1,7,'2026-08-13 09:45:00','2026-08-08 13:05:27',NULL),(22,11,9,1,7,'2026-08-13 13:30:00','2026-08-08 13:05:27',NULL),(23,10,10,1,7,'2026-08-14 08:00:00','2026-08-08 13:05:27',NULL),(24,1,9,1,7,'2026-08-14 13:30:00','2026-08-08 13:05:27',NULL),(25,33,11,1,7,'2026-08-14 13:30:00','2026-08-08 13:05:27',NULL),(26,14,10,1,7,'2026-08-14 11:25:00','2026-08-08 13:05:28',NULL),(27,21,9,1,7,'2026-08-14 13:50:00','2026-08-08 13:05:28',NULL),(28,30,8,1,7,'2026-08-14 08:00:00','2026-08-08 13:05:28',NULL),(29,22,10,1,1,'2026-08-15 08:00:00','2026-08-08 13:05:28',NULL),(30,8,8,1,1,'2026-08-15 08:00:00','2026-08-08 13:05:28',NULL),(31,9,9,1,1,'2026-08-15 13:30:00','2026-08-08 13:05:28',NULL),(32,10,9,1,1,'2026-08-15 16:05:00','2026-08-08 13:05:28',NULL),(33,7,8,1,1,'2026-08-15 10:05:00','2026-08-08 13:05:29',NULL),(34,34,9,1,1,'2026-08-15 18:00:00','2026-08-08 13:05:29',NULL),(35,19,10,1,1,'2026-08-15 08:45:00','2026-08-08 13:05:29',NULL),(36,1,10,1,1,'2026-08-17 08:00:00','2026-08-08 13:05:29',NULL),(37,12,9,1,1,'2026-08-18 13:30:00','2026-08-08 13:05:29',NULL),(38,7,8,1,1,'2026-08-18 08:00:00','2026-08-08 13:05:29',NULL),(39,9,9,1,1,'2026-08-19 13:30:00','2026-08-08 13:05:29',NULL),(40,34,8,1,1,'2026-08-19 08:00:00','2026-08-08 13:05:30',NULL),(41,7,10,1,1,'2026-08-19 08:00:00','2026-08-08 13:05:30',NULL),(42,16,8,1,1,'2026-08-20 08:00:00','2026-08-08 13:05:30',NULL),(43,31,10,1,1,'2026-08-20 08:00:00','2026-08-08 13:05:30',NULL),(44,5,11,1,1,'2026-08-20 13:30:00','2026-08-08 13:05:30',NULL),(45,12,9,1,1,'2026-08-20 13:30:00','2026-08-08 13:05:30',NULL),(46,32,11,1,1,'2026-08-21 13:30:00','2026-08-08 13:05:30',NULL),(47,24,8,1,1,'2026-08-21 08:00:00','2026-08-08 13:06:13',NULL),(48,9,10,1,1,'2026-08-21 08:00:00','2026-08-08 13:06:14',NULL),(49,7,9,1,1,'2026-08-21 13:30:00','2026-08-08 13:06:14',NULL),(50,10,8,1,1,'2026-08-21 08:55:00','2026-08-08 13:06:14',NULL),(51,5,10,1,1,'2026-08-22 08:00:00','2026-08-08 13:06:14',NULL),(52,26,9,1,1,'2026-08-22 13:30:00','2026-08-08 13:06:14',NULL),(53,7,8,1,1,'2026-08-22 08:00:00','2026-08-08 13:06:14',NULL),(54,8,10,1,1,'2026-08-22 11:05:00','2026-08-08 13:06:14',NULL),(55,32,11,1,1,'2026-08-22 13:30:00','2026-08-08 13:06:15',NULL),(56,1,9,1,1,'2026-08-24 13:30:00','2026-08-08 13:06:15',NULL),(57,12,10,1,1,'2026-08-25 08:00:00','2026-08-08 13:06:15',NULL),(58,17,10,1,1,'2026-08-25 08:35:00','2026-08-08 13:06:15',NULL),(59,25,11,1,1,'2026-08-25 13:30:00','2026-08-08 13:06:15',NULL),(60,8,10,1,1,'2026-08-25 09:20:00','2026-08-08 13:06:15',NULL),(61,12,10,1,1,'2026-08-26 08:00:00','2026-08-08 13:06:15',NULL),(62,12,11,1,1,'2026-08-26 13:30:00','2026-08-08 13:06:16',NULL),(63,9,10,1,1,'2026-08-26 10:05:00','2026-08-08 13:06:16',NULL),(64,23,8,1,1,'2026-08-27 08:00:00','2026-08-08 13:06:16',NULL),(65,9,9,1,1,'2026-08-27 13:30:00','2026-08-08 13:06:16',NULL),(66,8,11,1,1,'2026-08-27 13:30:00','2026-08-08 13:06:16',NULL),(67,5,9,1,1,'2026-08-28 13:30:00','2026-08-08 13:06:16',NULL),(68,5,11,1,1,'2026-08-28 13:30:00','2026-08-08 13:06:16',NULL),(69,10,9,1,1,'2026-08-28 14:05:00','2026-08-08 13:06:17',NULL),(70,11,9,1,1,'2026-08-28 15:30:00','2026-08-08 13:06:17',NULL),(71,7,9,1,1,'2026-08-28 16:35:00','2026-08-08 13:06:17',NULL),(72,10,10,1,1,'2026-08-29 08:00:00','2026-08-08 13:06:17',NULL),(73,9,11,1,1,'2026-08-29 13:30:00','2026-08-08 13:06:17',NULL),(74,8,10,1,1,'2026-08-29 10:05:00','2026-08-08 13:06:17',NULL),(75,23,10,1,1,'2026-08-29 11:10:00','2026-08-08 13:06:17',NULL),(76,28,11,1,1,'2026-08-29 16:05:00','2026-08-08 13:06:18',NULL),(77,19,11,1,1,'2026-08-31 13:30:00','2026-08-08 13:06:18',NULL),(78,23,9,1,1,'2026-08-31 13:30:00','2026-08-08 13:06:18',NULL),(79,12,11,1,1,'2026-08-31 14:50:00','2026-08-08 13:06:18',NULL),(80,31,11,1,1,'2026-09-01 13:30:00','2026-08-08 13:06:18',NULL),(81,8,10,1,1,'2026-09-01 08:00:00','2026-08-08 13:06:19',NULL),(82,5,8,1,1,'2026-09-01 08:00:00','2026-08-08 13:06:19',NULL),(83,9,8,1,1,'2026-09-02 08:00:00','2026-08-08 13:06:19',NULL),(84,22,11,1,1,'2026-09-02 13:30:00','2026-08-08 13:06:19',NULL),(85,10,9,1,1,'2026-09-03 13:30:00','2026-08-08 13:06:19',NULL),(86,11,9,1,1,'2026-09-03 14:35:00','2026-08-08 13:06:19',NULL),(87,30,9,1,1,'2026-09-03 15:40:00','2026-08-08 13:06:19',NULL),(88,12,10,1,1,'2026-09-04 08:00:00','2026-08-08 13:06:20',NULL),(89,31,9,1,1,'2026-09-04 13:30:00','2026-08-08 13:06:20',NULL),(90,12,9,1,1,'2026-09-04 16:35:00','2026-08-08 13:06:20',NULL),(91,30,9,1,1,'2026-09-04 17:55:00','2026-08-08 13:06:20',NULL),(92,12,10,1,1,'2026-09-05 08:00:00','2026-08-08 13:06:21',NULL),(93,1,11,1,1,'2026-09-05 13:30:00','2026-08-08 13:06:21',NULL),(94,25,8,1,1,'2026-09-05 08:00:00','2026-08-08 13:06:21',NULL),(95,7,8,1,1,'2026-09-05 10:35:00','2026-08-08 13:06:22',NULL),(96,10,10,1,1,'2026-09-05 13:35:00','2026-08-08 13:06:22',NULL),(97,10,8,1,4,'2026-07-10 09:30:00','2026-08-08 13:11:50',NULL),(98,12,10,1,4,'2026-07-10 09:30:00','2026-08-08 13:11:50',NULL),(99,7,11,1,4,'2026-07-10 15:00:00','2026-08-08 13:11:51',NULL),(100,8,9,1,4,'2026-07-10 15:00:00','2026-08-08 13:11:52',NULL),(101,9,9,1,3,'2026-07-11 15:00:00','2026-08-08 13:11:53',NULL),(102,5,8,1,4,'2026-07-11 09:30:00','2026-08-08 13:11:53',NULL),(103,1,10,1,4,'2026-07-11 11:00:00','2026-08-08 13:11:54',NULL),(104,29,11,1,6,'2026-07-13 15:00:00','2026-08-08 13:11:54',NULL),(105,20,8,1,4,'2026-07-13 09:30:00','2026-08-08 13:11:55',NULL),(106,8,9,1,7,'2026-07-14 15:00:00','2026-08-08 13:11:56',NULL),(107,34,10,1,4,'2026-07-14 09:30:00','2026-08-08 13:11:56',NULL),(108,7,10,1,4,'2026-07-14 11:00:00','2026-08-08 13:11:56',NULL),(109,7,10,1,6,'2026-07-15 09:30:00','2026-08-08 13:11:57',NULL),(110,27,9,1,7,'2026-07-15 15:00:00','2026-08-08 13:11:57',NULL),(111,9,9,1,4,'2026-07-15 16:30:00','2026-08-08 13:11:58',NULL),(112,12,9,1,4,'2026-07-16 15:00:00','2026-08-08 13:11:59',NULL),(113,1,11,1,4,'2026-07-16 15:00:00','2026-08-08 13:11:59',NULL),(114,7,11,1,4,'2026-07-16 16:30:00','2026-08-08 13:12:00',NULL),(115,11,10,1,4,'2026-07-17 09:30:00','2026-08-08 13:12:01',NULL),(116,8,11,1,7,'2026-07-17 15:00:00','2026-08-08 13:12:01',NULL),(117,11,11,1,7,'2026-07-17 16:30:00','2026-08-08 13:12:02',NULL),(118,9,11,1,4,'2026-07-18 15:00:00','2026-08-08 13:12:02',NULL),(119,34,8,1,3,'2026-07-18 09:30:00','2026-08-08 13:12:03',NULL),(120,9,11,1,4,'2026-07-18 16:30:00','2026-08-08 13:12:03',NULL),(121,10,8,1,4,'2026-07-20 09:30:00','2026-08-08 13:12:04',NULL),(122,20,10,1,4,'2026-07-20 09:30:00','2026-08-08 13:12:05',NULL),(123,12,10,1,4,'2026-07-21 09:30:00','2026-08-08 13:12:06',NULL),(124,12,9,1,4,'2026-07-21 15:00:00','2026-08-08 13:12:06',NULL),(125,9,11,1,4,'2026-07-22 15:00:00','2026-08-08 13:12:07',NULL),(126,12,10,1,4,'2026-07-22 09:30:00','2026-08-08 13:12:08',NULL),(127,8,11,1,4,'2026-07-22 16:30:00','2026-08-08 13:12:08',NULL),(128,8,10,1,4,'2026-07-23 09:30:00','2026-08-08 13:12:09',NULL),(129,1,8,1,4,'2026-07-23 09:30:00','2026-08-08 13:12:10',NULL),(130,33,9,1,4,'2026-07-23 15:00:00','2026-08-08 13:12:11',NULL),(131,12,8,1,4,'2026-07-24 09:30:00','2026-08-08 13:12:11',NULL),(132,10,10,1,4,'2026-07-24 09:30:00','2026-08-08 13:12:12',NULL),(133,17,11,1,7,'2026-07-24 15:00:00','2026-08-08 13:12:13',NULL),(134,34,9,1,4,'2026-07-25 15:00:00','2026-08-08 13:12:13',NULL),(135,11,10,1,4,'2026-07-25 09:30:00','2026-08-08 13:12:14',NULL),(136,17,11,1,4,'2026-07-25 16:30:00','2026-08-08 13:12:15',NULL),(137,1,9,1,4,'2026-07-27 15:00:00','2026-08-08 13:12:16',NULL),(138,1,11,1,6,'2026-07-27 15:00:00','2026-08-08 13:12:16',NULL),(139,12,10,1,7,'2026-07-28 09:30:00','2026-08-08 13:12:17',NULL),(140,23,9,1,4,'2026-07-28 15:00:00','2026-08-08 13:12:17',NULL),(141,8,11,1,4,'2026-07-28 15:00:00','2026-08-08 13:12:18',NULL),(142,7,9,1,3,'2026-07-29 15:00:00','2026-08-08 13:12:19',NULL),(143,10,9,1,4,'2026-07-29 16:30:00','2026-08-08 13:12:19',NULL),(144,12,10,1,4,'2026-07-29 09:30:00','2026-08-08 13:12:20',NULL),(145,9,9,1,7,'2026-07-30 15:00:00','2026-08-08 13:12:20',NULL),(146,12,11,1,4,'2026-07-30 15:00:00','2026-08-08 13:12:21',NULL),(147,15,9,1,4,'2026-07-30 16:30:00','2026-08-08 13:12:21',NULL),(148,5,10,1,4,'2026-07-30 09:30:00','2026-08-08 13:12:22',NULL),(149,9,8,1,4,'2026-07-31 09:30:00','2026-08-08 13:12:23',NULL),(150,13,10,1,7,'2026-07-31 09:30:00','2026-08-08 13:12:23',NULL),(151,12,11,1,4,'2026-07-31 15:00:00','2026-08-08 13:12:24',NULL),(152,22,9,1,4,'2026-08-01 15:00:00','2026-08-08 13:12:25',NULL),(153,20,9,1,4,'2026-08-01 16:30:00','2026-08-08 13:12:25',NULL),(154,25,10,1,4,'2026-08-01 09:30:00','2026-08-08 13:12:26',NULL),(155,9,8,1,7,'2026-08-01 09:30:00','2026-08-08 13:12:26',NULL),(156,5,9,1,4,'2026-08-01 17:45:00','2026-08-08 13:12:27',NULL),(157,10,8,1,7,'2026-08-03 09:30:00','2026-08-08 13:12:28',NULL),(158,1,9,1,3,'2026-08-03 15:00:00','2026-08-08 13:12:28',NULL),(159,10,11,1,7,'2026-08-04 15:00:00','2026-08-08 13:12:29',NULL),(160,11,9,1,4,'2026-08-04 15:00:00','2026-08-08 13:12:29',NULL),(161,1,10,1,4,'2026-08-04 09:30:00','2026-08-08 13:12:30',NULL),(162,32,9,1,7,'2026-08-05 15:00:00','2026-08-08 13:12:31',NULL),(163,10,8,1,4,'2026-08-05 09:30:00','2026-08-08 13:12:31',NULL),(164,13,8,1,4,'2026-08-06 09:30:00','2026-08-08 13:12:32',NULL),(165,12,11,1,4,'2026-08-06 15:00:00','2026-08-08 13:12:33',NULL),(166,14,9,1,3,'2026-08-06 15:00:00','2026-08-08 13:12:33',NULL),(167,8,8,1,7,'2026-08-06 11:00:00','2026-08-08 13:12:33',NULL),(168,1,9,1,7,'2026-08-07 15:00:00','2026-08-08 13:12:34',NULL),(169,5,8,1,4,'2026-08-07 09:30:00','2026-08-08 13:12:34',NULL),(170,8,10,1,7,'2026-08-07 09:30:00','2026-08-08 13:12:35',NULL),(171,1,10,1,4,'2026-08-07 11:00:00','2026-08-08 13:12:35',NULL),(172,1,10,1,7,'2026-08-11 09:00:00','2026-08-08 13:25:01','Reserva desde el portal'),(173,1,10,1,4,'2026-08-08 08:30:00','2026-08-08 13:25:54','Para valorar'),(174,11,10,1,1,'2026-08-29 14:00:00','2026-08-08 16:59:58',NULL);
/*!40000 ALTER TABLE `cita` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_cita_bi
BEFORE INSERT ON cita FOR EACH ROW
BEGIN
  IF fn_es_personal(NEW.id_usuario) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cita debe asignarse a un usuario del personal.';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_cita_bu
BEFORE UPDATE ON cita FOR EACH ROW
BEGIN
  IF fn_es_personal(NEW.id_usuario) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cita debe asignarse a un usuario del personal.';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `cita_pedido`
--

DROP TABLE IF EXISTS `cita_pedido`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cita_pedido` (
  `id_pedido` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cita` int(10) unsigned NOT NULL,
  `observaciones` varchar(300) NOT NULL,
  `atendido` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pedido`),
  KEY `idx_citapedido_cita` (`id_cita`),
  CONSTRAINT `fk_citapedido_cita` FOREIGN KEY (`id_cita`) REFERENCES `cita` (`id_cita`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cita_pedido`
--

LOCK TABLES `cita_pedido` WRITE;
/*!40000 ALTER TABLE `cita_pedido` DISABLE KEYS */;
/*!40000 ALTER TABLE `cita_pedido` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cita_servicio`
--

DROP TABLE IF EXISTS `cita_servicio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cita_servicio` (
  `id_cita_servicio` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cita` int(10) unsigned NOT NULL,
  `id_servicio` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id_cita_servicio`),
  UNIQUE KEY `uq_cita_servicio` (`id_cita`,`id_servicio`),
  KEY `idx_cs_servicio` (`id_servicio`),
  KEY `fk_citaserv_usuario` (`id_usuario`),
  CONSTRAINT `fk_citaserv_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cs_cita` FOREIGN KEY (`id_cita`) REFERENCES `cita` (`id_cita`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cs_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicio` (`id_servicio`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=941 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cita_servicio`
--

LOCK TABLES `cita_servicio` WRITE;
/*!40000 ALTER TABLE `cita_servicio` DISABLE KEYS */;
INSERT INTO `cita_servicio` VALUES (3,3,5,NULL),(4,3,11,NULL),(5,4,10,NULL),(6,4,12,NULL),(7,5,3,NULL),(8,5,5,NULL),(9,5,7,NULL),(10,6,5,NULL),(11,6,10,NULL),(12,7,8,NULL),(13,8,4,NULL),(14,8,13,NULL),(15,9,5,NULL),(16,10,13,NULL),(17,10,14,NULL),(18,11,6,NULL),(19,11,11,NULL),(20,12,3,NULL),(21,13,5,NULL),(22,13,10,NULL),(23,13,13,NULL),(24,14,9,NULL),(25,15,3,NULL),(26,16,5,NULL),(27,16,6,NULL),(28,17,8,NULL),(29,17,13,NULL),(30,18,5,NULL),(31,18,14,NULL),(32,19,13,NULL),(33,19,14,NULL),(34,20,8,NULL),(35,20,10,NULL),(36,21,7,NULL),(37,22,8,NULL),(38,22,9,NULL),(39,23,7,NULL),(40,23,13,NULL),(41,24,14,NULL),(42,25,10,NULL),(43,26,8,NULL),(44,27,8,NULL),(45,27,11,NULL),(46,27,12,NULL),(47,28,9,NULL),(48,29,12,NULL),(49,30,3,NULL),(50,30,11,NULL),(51,31,7,NULL),(52,32,8,NULL),(53,32,13,NULL),(54,33,5,NULL),(55,33,8,NULL),(56,34,10,NULL),(57,35,12,NULL),(58,36,3,NULL),(59,36,10,NULL),(60,37,8,NULL),(61,38,13,NULL),(62,38,14,NULL),(63,39,12,NULL),(64,40,8,NULL),(65,40,11,NULL),(66,41,4,NULL),(67,41,13,NULL),(68,42,9,NULL),(69,42,14,NULL),(70,43,3,NULL),(71,44,11,NULL),(72,45,13,NULL),(73,46,3,NULL),(74,46,10,NULL),(75,47,13,NULL),(76,48,3,NULL),(77,48,11,NULL),(78,49,6,NULL),(79,50,8,NULL),(80,50,10,NULL),(81,50,14,NULL),(82,51,9,NULL),(83,52,3,NULL),(84,52,11,NULL),(85,53,9,NULL),(86,54,5,NULL),(87,55,11,NULL),(88,56,13,NULL),(89,57,5,NULL),(90,58,12,NULL),(91,59,8,NULL),(92,59,10,NULL),(93,59,11,NULL),(94,60,10,NULL),(95,60,13,NULL),(96,61,6,NULL),(97,62,10,NULL),(98,63,10,NULL),(99,64,11,NULL),(100,65,11,NULL),(101,66,3,NULL),(102,66,5,NULL),(103,67,5,NULL),(104,68,6,NULL),(105,69,10,NULL),(106,69,12,NULL),(107,70,8,NULL),(108,71,14,NULL),(109,72,6,NULL),(110,73,7,NULL),(111,74,4,NULL),(112,74,5,NULL),(113,75,12,NULL),(114,76,10,NULL),(115,76,14,NULL),(116,77,11,NULL),(117,78,7,NULL),(118,78,14,NULL),(119,79,4,NULL),(120,80,10,NULL),(121,80,13,NULL),(122,81,5,NULL),(123,81,10,NULL),(124,82,4,NULL),(125,82,5,NULL),(126,83,6,NULL),(127,83,10,NULL),(128,83,14,NULL),(129,84,7,NULL),(130,85,8,NULL),(131,86,4,NULL),(132,86,5,NULL),(133,87,3,NULL),(134,87,11,NULL),(135,88,6,NULL),(136,89,7,NULL),(137,90,8,NULL),(138,90,14,NULL),(139,91,4,NULL),(140,91,5,NULL),(141,92,3,NULL),(142,92,7,NULL),(143,92,11,NULL),(144,93,7,NULL),(145,94,7,NULL),(146,95,10,NULL),(147,96,8,NULL),(148,96,12,NULL),(164,97,4,NULL),(166,98,4,NULL),(168,99,4,NULL),(169,99,6,NULL),(172,100,4,NULL),(174,101,4,NULL),(175,102,4,NULL),(176,102,13,NULL),(179,103,3,NULL),(181,104,12,NULL),(182,105,5,NULL),(184,106,10,NULL),(185,106,12,NULL),(188,107,3,NULL),(190,108,10,NULL),(191,108,13,NULL),(194,109,5,NULL),(195,110,13,NULL),(197,111,3,NULL),(199,112,7,NULL),(201,113,8,NULL),(202,113,12,NULL),(205,114,14,NULL),(207,115,4,NULL),(208,115,12,NULL),(211,116,5,NULL),(213,117,8,NULL),(214,117,13,NULL),(217,118,7,NULL),(219,119,6,NULL),(220,120,3,NULL),(221,120,10,NULL),(224,121,4,NULL),(225,121,10,NULL),(228,122,3,NULL),(229,122,10,NULL),(232,123,10,NULL),(233,123,13,NULL),(236,124,9,NULL),(238,125,9,NULL),(239,125,12,NULL),(242,126,5,NULL),(243,126,10,NULL),(246,127,6,NULL),(248,128,9,NULL),(250,129,13,NULL),(251,129,14,NULL),(254,130,6,NULL),(256,131,5,NULL),(257,131,12,NULL),(260,132,14,NULL),(262,133,7,NULL),(264,134,6,NULL),(266,135,12,NULL),(268,136,12,NULL),(270,137,4,NULL),(271,137,9,NULL),(274,138,3,NULL),(275,139,10,NULL),(276,139,11,NULL),(279,140,5,NULL),(280,140,7,NULL),(283,141,14,NULL),(285,142,6,NULL),(286,143,5,NULL),(288,144,6,NULL),(289,144,12,NULL),(292,145,12,NULL),(294,146,8,NULL),(295,146,9,NULL),(298,147,3,NULL),(299,147,14,NULL),(302,148,5,NULL),(304,149,12,NULL),(306,150,8,NULL),(308,151,4,NULL),(309,151,14,NULL),(312,152,10,NULL),(314,153,7,NULL),(316,154,10,NULL),(318,155,8,NULL),(320,156,10,NULL),(322,157,14,NULL),(324,158,11,NULL),(325,159,4,NULL),(326,159,11,NULL),(329,160,6,NULL),(331,161,5,NULL),(333,162,10,NULL),(334,162,11,NULL),(337,163,11,NULL),(339,164,5,NULL),(341,165,3,NULL),(342,165,5,NULL),(345,166,5,NULL),(346,166,10,NULL),(347,167,10,NULL),(348,167,13,NULL),(351,168,13,NULL),(353,169,4,NULL),(355,170,3,NULL),(356,170,4,NULL),(359,171,11,NULL),(361,172,3,NULL),(362,173,4,NULL),(364,174,4,NULL);
/*!40000 ALTER TABLE `cita_servicio` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_citaserv_bi
BEFORE INSERT ON cita_servicio FOR EACH ROW
BEGIN
  DECLARE v_usuario  INT UNSIGNED DEFAULT NULL;
  DECLARE v_cliente  INT UNSIGNED DEFAULT NULL;
  DECLARE v_dia      DATE DEFAULT NULL;
  DECLARE v_repetido INT DEFAULT 0;
  DECLARE v_nombre   VARCHAR(100) DEFAULT '';
  DECLARE v_msg      VARCHAR(255);

  SELECT c.id_usuario, c.id_cliente, DATE(c.fecha_hora)
    INTO v_usuario, v_cliente, v_dia
    FROM cita c WHERE c.id_cita = NEW.id_cita;

  IF fn_puede_realizar(v_usuario, NEW.id_servicio) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El profesional de la cita no esta habilitado para ese servicio.';
  END IF;

  SELECT COUNT(*) INTO v_repetido
    FROM cita_servicio cs
    JOIN cita c        ON c.id_cita = cs.id_cita
    JOIN estado_cita e ON e.id_estado_cita = c.id_estado_cita
   WHERE cs.id_servicio = NEW.id_servicio
     AND c.id_cliente   = v_cliente
     AND DATE(c.fecha_hora) = v_dia
     AND e.bloquea_agenda = 1;

  IF v_repetido > 0 THEN
    SELECT s.nombre INTO v_nombre FROM servicio s WHERE s.id_servicio = NEW.id_servicio;
    SET v_msg = CONCAT('Esa clienta ya tiene "', v_nombre,
                       '" agendado para ese mismo dia. No se repite el mismo servicio en el dia: ',
                       'cambia la fecha, o cancela la otra cita primero.');
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = v_msg;
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `cliente`
--

DROP TABLE IF EXISTS `cliente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cliente` (
  `id_cliente` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  `observaciones` varchar(300) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `id_persona` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `uq_cliente_usuario` (`id_usuario`),
  KEY `fk_clie_persona` (`id_persona`),
  CONSTRAINT `fk_clie_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE,
  CONSTRAINT `fk_cliente_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cliente`
--

LOCK TABLES `cliente` WRITE;
/*!40000 ALTER TABLE `cliente` DISABLE KEYS */;
INSERT INTO `cliente` VALUES (1,2,'2026-07-14 19:42:29',NULL,1,2),(5,6,'2026-07-18 21:58:28',NULL,1,3),(7,NULL,'2026-08-08 13:00:38',NULL,1,13),(8,NULL,'2026-08-08 13:00:38',NULL,1,14),(9,NULL,'2026-08-08 13:00:38',NULL,1,15),(10,NULL,'2026-08-08 13:00:38',NULL,1,16),(11,NULL,'2026-08-08 13:00:39',NULL,1,17),(12,NULL,'2026-08-08 13:00:39',NULL,1,18),(13,NULL,'2026-08-08 13:00:39',NULL,1,19),(14,NULL,'2026-08-08 13:00:39',NULL,1,20),(15,NULL,'2026-08-08 13:00:39',NULL,1,21),(16,NULL,'2026-08-08 13:00:39',NULL,1,22),(17,NULL,'2026-08-08 13:00:40',NULL,1,23),(18,NULL,'2026-08-08 13:00:40',NULL,1,24),(19,NULL,'2026-08-08 13:00:40',NULL,1,25),(20,NULL,'2026-08-08 13:00:40',NULL,1,26),(21,NULL,'2026-08-08 13:00:40',NULL,1,27),(22,NULL,'2026-08-08 13:00:40',NULL,1,28),(23,NULL,'2026-08-08 13:00:40',NULL,1,29),(24,NULL,'2026-08-08 13:00:41',NULL,1,30),(25,NULL,'2026-08-08 13:00:41',NULL,1,31),(26,NULL,'2026-08-08 13:00:41',NULL,1,32),(27,NULL,'2026-08-08 13:00:41',NULL,1,33),(28,NULL,'2026-08-08 13:01:59',NULL,1,34),(29,NULL,'2026-08-08 13:01:59',NULL,1,35),(30,NULL,'2026-08-08 13:01:59',NULL,1,36),(31,NULL,'2026-08-08 13:01:59',NULL,1,37),(32,NULL,'2026-08-08 13:01:59',NULL,1,38),(33,NULL,'2026-08-08 13:01:59',NULL,1,39),(34,NULL,'2026-08-08 13:01:59',NULL,1,40),(35,NULL,'2026-08-08 13:23:12',NULL,1,41),(36,NULL,'2026-08-08 13:23:12',NULL,1,42),(37,NULL,'2026-08-08 13:23:12',NULL,1,43);
/*!40000 ALTER TABLE `cliente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cobro`
--

DROP TABLE IF EXISTS `cobro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cobro` (
  `id_cobro` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_factura` int(10) unsigned DEFAULT NULL,
  `id_cita` int(10) unsigned DEFAULT NULL,
  `id_metodo_pago` int(10) unsigned NOT NULL,
  `id_estado_cobro` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_caja` int(10) unsigned DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `monto` decimal(14,2) NOT NULL DEFAULT 0.00,
  `referencia` varchar(100) DEFAULT NULL,
  `observaciones` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`id_cobro`),
  KEY `idx_cobro_factura` (`id_factura`),
  KEY `idx_cobro_cita` (`id_cita`),
  KEY `idx_cobro_metodo` (`id_metodo_pago`),
  KEY `idx_cobro_estado` (`id_estado_cobro`),
  KEY `idx_cobro_usuario` (`id_usuario`),
  KEY `idx_cobro_caja` (`id_caja`),
  KEY `idx_cobro_fecha` (`fecha`),
  CONSTRAINT `fk_cobro_caja` FOREIGN KEY (`id_caja`) REFERENCES `caja` (`id_caja`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_cobro_cita` FOREIGN KEY (`id_cita`) REFERENCES `cita` (`id_cita`) ON UPDATE CASCADE,
  CONSTRAINT `fk_cobro_estado` FOREIGN KEY (`id_estado_cobro`) REFERENCES `estado_cobro` (`id_estado_cobro`) ON UPDATE CASCADE,
  CONSTRAINT `fk_cobro_factura` FOREIGN KEY (`id_factura`) REFERENCES `factura` (`id_factura`) ON UPDATE CASCADE,
  CONSTRAINT `fk_cobro_metodo` FOREIGN KEY (`id_metodo_pago`) REFERENCES `metodo_pago` (`id_metodo_pago`) ON UPDATE CASCADE,
  CONSTRAINT `fk_cobro_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `chk_cobro_monto` CHECK (`monto` >= 0),
  CONSTRAINT `chk_cobro_destino` CHECK (`id_factura` is not null and `id_cita` is null or `id_factura` is null and `id_cita` is not null)
) ENGINE=InnoDB AUTO_INCREMENT=203 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cobro`
--

LOCK TABLES `cobro` WRITE;
/*!40000 ALTER TABLE `cobro` DISABLE KEYS */;
INSERT INTO `cobro` VALUES (2,2,NULL,1,1,12,3,'2026-08-08 13:09:04',115000.00,NULL,NULL),(3,3,NULL,3,1,12,3,'2026-08-08 13:09:04',100000.00,NULL,NULL),(4,4,NULL,4,1,12,3,'2026-08-08 13:09:04',115000.00,NULL,NULL),(5,5,NULL,1,1,12,3,'2026-08-08 13:09:04',20000.00,NULL,NULL),(6,5,NULL,2,1,12,3,'2026-08-08 13:09:04',20000.00,NULL,NULL),(7,6,NULL,1,1,12,3,'2026-08-08 13:09:05',90000.00,NULL,NULL),(8,7,NULL,3,1,12,3,'2026-08-08 13:09:05',220000.00,NULL,NULL),(9,8,NULL,4,1,12,3,'2026-08-08 13:09:05',465000.00,NULL,NULL),(10,9,NULL,1,1,12,3,'2026-08-08 13:09:05',75000.00,NULL,NULL),(11,9,NULL,2,1,12,3,'2026-08-08 13:09:05',75000.00,NULL,NULL),(12,10,NULL,3,1,12,3,'2026-07-10 13:11:50',50000.00,NULL,NULL),(13,11,NULL,1,1,12,3,'2026-07-10 13:11:51',50000.00,NULL,NULL),(14,12,NULL,3,1,12,3,'2026-07-10 13:11:52',330000.00,NULL,NULL),(15,14,NULL,4,1,12,3,'2026-07-11 13:11:53',115000.00,NULL,NULL),(16,15,NULL,1,1,12,3,'2026-07-11 13:11:54',45000.00,NULL,NULL),(17,15,NULL,2,1,12,3,'2026-07-11 13:11:54',30000.00,NULL,NULL),(18,16,NULL,1,1,12,3,'2026-07-13 13:11:55',40000.00,NULL,NULL),(19,17,NULL,4,1,12,3,'2026-07-14 13:11:56',75000.00,NULL,NULL),(20,18,NULL,1,1,12,3,'2026-07-14 13:11:57',75000.00,NULL,NULL),(21,18,NULL,2,1,12,3,'2026-07-14 13:11:57',50000.00,NULL,NULL),(22,19,NULL,1,1,12,3,'2026-07-15 13:11:58',75000.00,NULL,NULL),(23,20,NULL,4,1,12,3,'2026-07-16 13:11:59',350000.00,NULL,NULL),(24,21,NULL,3,1,12,3,'2026-07-16 13:12:00',205000.00,NULL,NULL),(25,23,NULL,4,1,12,3,'2026-07-17 13:12:01',105000.00,NULL,NULL),(26,25,NULL,4,1,12,3,'2026-07-18 13:12:04',135000.00,NULL,NULL),(27,26,NULL,4,1,12,3,'2026-07-20 13:12:05',110000.00,NULL,NULL),(28,27,NULL,3,1,12,3,'2026-07-20 13:12:05',135000.00,NULL,NULL),(29,28,NULL,1,1,12,3,'2026-07-21 13:12:06',125000.00,NULL,NULL),(30,29,NULL,4,1,12,3,'2026-07-21 13:12:07',420000.00,NULL,NULL),(31,31,NULL,1,1,12,3,'2026-07-22 13:12:08',60000.00,NULL,NULL),(32,31,NULL,2,1,12,3,'2026-07-22 13:12:08',40000.00,NULL,NULL),(33,32,NULL,4,1,12,3,'2026-07-22 13:12:09',280000.00,NULL,NULL),(34,33,NULL,1,1,12,3,'2026-07-23 13:12:10',420000.00,NULL,NULL),(35,34,NULL,4,1,12,3,'2026-07-23 13:12:10',90000.00,NULL,NULL),(36,35,NULL,1,1,12,3,'2026-07-23 13:12:11',280000.00,NULL,NULL),(37,36,NULL,3,1,12,3,'2026-07-24 13:12:12',95000.00,NULL,NULL),(38,37,NULL,3,1,12,3,'2026-07-24 13:12:12',25000.00,NULL,NULL),(39,38,NULL,4,1,12,3,'2026-07-25 13:12:14',280000.00,NULL,NULL),(40,39,NULL,1,1,12,3,'2026-07-25 13:12:14',55000.00,NULL,NULL),(41,40,NULL,4,1,12,3,'2026-07-25 13:12:15',55000.00,NULL,NULL),(42,41,NULL,3,1,12,3,'2026-07-27 13:12:16',470000.00,NULL,NULL),(43,42,NULL,1,1,12,3,'2026-07-28 13:12:18',390000.00,NULL,NULL),(44,43,NULL,1,1,12,3,'2026-07-28 13:12:18',25000.00,NULL,NULL),(45,44,NULL,1,1,12,3,'2026-07-29 13:12:19',24000.00,NULL,NULL),(46,44,NULL,2,1,12,3,'2026-07-29 13:12:19',16000.00,NULL,NULL),(47,45,NULL,1,1,12,3,'2026-07-29 13:12:20',201000.00,NULL,NULL),(48,45,NULL,2,1,12,3,'2026-07-29 13:12:20',134000.00,NULL,NULL),(49,46,NULL,1,1,12,3,'2026-07-30 13:12:21',570000.00,NULL,NULL),(50,47,NULL,1,1,12,3,'2026-07-30 13:12:22',60000.00,NULL,NULL),(51,47,NULL,2,1,12,3,'2026-07-30 13:12:22',40000.00,NULL,NULL),(52,48,NULL,4,1,12,3,'2026-07-30 13:12:22',40000.00,NULL,NULL),(53,49,NULL,3,1,12,3,'2026-07-31 13:12:23',55000.00,NULL,NULL),(54,50,NULL,1,1,12,3,'2026-07-31 13:12:24',45000.00,NULL,NULL),(55,50,NULL,2,1,12,3,'2026-07-31 13:12:24',30000.00,NULL,NULL),(56,51,NULL,3,1,12,3,'2026-08-01 13:12:25',60000.00,NULL,NULL),(57,52,NULL,4,1,12,3,'2026-08-01 13:12:26',350000.00,NULL,NULL),(58,53,NULL,4,1,12,3,'2026-08-01 13:12:26',60000.00,NULL,NULL),(59,54,NULL,3,1,12,3,'2026-08-01 13:12:27',60000.00,NULL,NULL),(60,55,NULL,1,1,12,3,'2026-08-04 13:12:30',168000.00,NULL,NULL),(61,55,NULL,2,1,12,3,'2026-08-04 13:12:30',112000.00,NULL,NULL),(62,56,NULL,1,1,12,3,'2026-08-04 13:12:30',24000.00,NULL,NULL),(63,56,NULL,2,1,12,3,'2026-08-04 13:12:30',16000.00,NULL,NULL),(64,57,NULL,1,1,12,3,'2026-08-05 13:12:32',180000.00,NULL,NULL),(65,58,NULL,3,1,12,3,'2026-08-06 13:12:32',40000.00,NULL,NULL),(66,59,NULL,3,1,12,3,'2026-08-06 13:12:33',109250.00,NULL,NULL),(67,60,NULL,1,1,12,3,'2026-08-07 13:12:35',30000.00,NULL,NULL),(68,60,NULL,2,1,12,3,'2026-08-07 13:12:35',20000.00,NULL,NULL),(69,61,NULL,1,3,12,3,'2026-08-07 13:12:36',108000.00,NULL,NULL),(70,61,NULL,2,3,12,3,'2026-08-07 13:12:36',72000.00,NULL,NULL),(71,NULL,12,1,1,1,3,'2026-08-08 13:15:02',100000.00,'Seña','Sena de reserva');
/*!40000 ALTER TABLE `cobro` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_cobro_au
AFTER UPDATE ON cobro FOR EACH ROW
BEGIN
  IF OLD.id_estado_cobro <> 3 AND NEW.id_estado_cobro = 3 THEN
    INSERT INTO auditoria (id_usuario, accion, modulo, tabla_afectada, id_registro, detalle)
    VALUES (COALESCE(@usuario_actual, NEW.id_usuario), 'ANULAR', 'Cobros', 'cobro', NEW.id_cobro,
            CONCAT('Cobro anulado. Monto: ', NEW.monto));
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `cobro_banco`
--

DROP TABLE IF EXISTS `cobro_banco`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cobro_banco` (
  `id_cobro_banco` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cobro` int(10) unsigned NOT NULL,
  `banco` varchar(80) NOT NULL,
  `nro_cheque` varchar(40) DEFAULT NULL,
  `nro_operacion` varchar(40) DEFAULT NULL,
  `fecha_emision` date DEFAULT NULL,
  PRIMARY KEY (`id_cobro_banco`),
  UNIQUE KEY `uq_cobro_banco` (`id_cobro`),
  CONSTRAINT `fk_cb_cobro` FOREIGN KEY (`id_cobro`) REFERENCES `cobro` (`id_cobro`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cobro_banco`
--

LOCK TABLES `cobro_banco` WRITE;
/*!40000 ALTER TABLE `cobro_banco` DISABLE KEYS */;
INSERT INTO `cobro_banco` VALUES (1,4,'Banco Continental',NULL,'OP9002','2026-08-08'),(2,9,'Banco Continental',NULL,'OP9006','2026-08-08'),(3,15,'Itaú',NULL,'OP22342','2026-07-11'),(4,19,'Itaú',NULL,'OP19500','2026-07-14'),(5,23,'Itaú',NULL,'OP93944','2026-07-16'),(6,25,'Itaú',NULL,'OP81304','2026-07-17'),(7,26,'Itaú',NULL,'OP50954','2026-07-18'),(8,27,'Itaú',NULL,'OP30606','2026-07-20'),(9,30,'Itaú',NULL,'OP43876','2026-07-21'),(10,33,'Itaú',NULL,'OP25908','2026-07-22'),(11,35,'Itaú',NULL,'OP12397','2026-07-23'),(12,39,'Itaú',NULL,'OP80680','2026-07-25'),(13,41,'Itaú',NULL,'OP41485','2026-07-25'),(14,52,'Itaú',NULL,'OP15852','2026-07-30'),(15,57,'Itaú',NULL,'OP77910','2026-08-01'),(16,58,'Itaú',NULL,'OP14432','2026-08-01');
/*!40000 ALTER TABLE `cobro_banco` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_cobrobanco_bi
BEFORE INSERT ON cobro_banco FOR EACH ROW
BEGIN
  DECLARE v_tipo VARCHAR(10);
  SELECT mp.tipo INTO v_tipo
  FROM cobro c JOIN metodo_pago mp ON mp.id_metodo_pago = c.id_metodo_pago
  WHERE c.id_cobro = NEW.id_cobro;

  IF v_tipo NOT IN ('BANCO', 'CHEQUE') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cobro no fue realizado por banco ni con cheque.';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `cobro_tarjeta`
--

DROP TABLE IF EXISTS `cobro_tarjeta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cobro_tarjeta` (
  `id_cobro_tarjeta` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cobro` int(10) unsigned NOT NULL,
  `marca` varchar(30) DEFAULT NULL,
  `tipo_tarjeta` varchar(30) NOT NULL,
  `cuotas` int(11) NOT NULL DEFAULT 1,
  `ultimos_4` char(4) DEFAULT NULL,
  `nro_boleta` varchar(60) DEFAULT NULL,
  `cod_autorizacion` varchar(60) DEFAULT NULL,
  PRIMARY KEY (`id_cobro_tarjeta`),
  UNIQUE KEY `uq_cobro_tarjeta` (`id_cobro`),
  CONSTRAINT `fk_ct_cobro` FOREIGN KEY (`id_cobro`) REFERENCES `cobro` (`id_cobro`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_ct_cuotas` CHECK (`cuotas` >= 1)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cobro_tarjeta`
--

LOCK TABLES `cobro_tarjeta` WRITE;
/*!40000 ALTER TABLE `cobro_tarjeta` DISABLE KEYS */;
INSERT INTO `cobro_tarjeta` VALUES (1,3,'Visa','CREDITO',3,'4821','B1001','AUT501'),(2,6,'Mastercard','DEBITO',1,'9930','B2003','AUT703'),(3,8,'Visa','CREDITO',3,'4821','B1005','AUT505'),(4,11,'Mastercard','DEBITO',1,'9930','B2007','AUT707'),(5,12,'Visa','CREDITO',1,'1234','B7722','A732'),(6,14,'Visa','CREDITO',1,'1234','B9578','A441'),(7,17,'Mastercard','DEBITO',1,'5678','B3056','A671'),(8,21,'Mastercard','DEBITO',1,'5678','B1901','A702'),(9,24,'Visa','CREDITO',1,'1234','B3443','A993'),(10,28,'Visa','CREDITO',1,'1234','B8144','A470'),(11,32,'Mastercard','DEBITO',1,'5678','B2509','A783'),(12,37,'Visa','CREDITO',1,'1234','B3369','A198'),(13,38,'Visa','CREDITO',1,'1234','B8652','A875'),(14,42,'Visa','CREDITO',1,'1234','B8341','A247'),(15,46,'Mastercard','DEBITO',1,'5678','B1961','A992'),(16,48,'Mastercard','DEBITO',1,'5678','B5255','A205'),(17,51,'Mastercard','DEBITO',1,'5678','B8008','A212'),(18,53,'Visa','CREDITO',1,'1234','B3704','A913'),(19,55,'Mastercard','DEBITO',1,'5678','B9228','A337'),(20,56,'Visa','CREDITO',1,'1234','B7664','A860'),(21,59,'Visa','CREDITO',1,'1234','B3605','A144'),(22,61,'Mastercard','DEBITO',1,'5678','B4871','A848'),(23,63,'Mastercard','DEBITO',1,'5678','B8251','A811'),(24,65,'Visa','CREDITO',1,'1234','B7857','A995'),(25,66,'Visa','CREDITO',1,'1234','B7297','A362'),(26,68,'Mastercard','DEBITO',1,'5678','B4107','A189'),(27,70,'Mastercard','DEBITO',1,'5678','B3574','A343');
/*!40000 ALTER TABLE `cobro_tarjeta` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_cobrotarjeta_bi
BEFORE INSERT ON cobro_tarjeta FOR EACH ROW
BEGIN
  DECLARE v_tipo VARCHAR(10);
  SELECT mp.tipo INTO v_tipo
  FROM cobro c JOIN metodo_pago mp ON mp.id_metodo_pago = c.id_metodo_pago
  WHERE c.id_cobro = NEW.id_cobro;

  IF v_tipo <> 'TARJETA' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cobro no fue realizado con tarjeta.';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `comision`
--

DROP TABLE IF EXISTS `comision`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comision` (
  `id_comision` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_servicio` int(10) unsigned DEFAULT NULL,
  `tipo` varchar(20) NOT NULL,
  `valor` decimal(12,2) NOT NULL DEFAULT 0.00,
  `vigente_desde` date NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_comision`),
  UNIQUE KEY `uq_comision` (`id_usuario`,`id_servicio`,`vigente_desde`),
  KEY `idx_comision_servicio` (`id_servicio`),
  CONSTRAINT `fk_comision_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicio` (`id_servicio`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_comision_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_comision_tipo` CHECK (`tipo` in ('PORCENTAJE','MONTO')),
  CONSTRAINT `chk_comision_valor` CHECK (`valor` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comision`
--

LOCK TABLES `comision` WRITE;
/*!40000 ALTER TABLE `comision` DISABLE KEYS */;
INSERT INTO `comision` VALUES (3,8,NULL,'PORCENTAJE',15.00,'2026-07-01',1);
/*!40000 ALTER TABLE `comision` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compra`
--

DROP TABLE IF EXISTS `compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compra` (
  `id_compra` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_proveedor` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  `id_estado_compra` int(10) unsigned NOT NULL,
  `id_condicion_venta` int(10) unsigned NOT NULL DEFAULT 1,
  `nro_factura_proveedor` varchar(20) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `observaciones` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`id_compra`),
  KEY `idx_compra_proveedor` (`id_proveedor`),
  KEY `idx_compra_usuario` (`id_usuario`),
  KEY `idx_compra_estado` (`id_estado_compra`),
  KEY `idx_compra_condicion` (`id_condicion_venta`),
  KEY `idx_compra_fecha` (`fecha`),
  KEY `fk_compra_sucursal` (`id_sucursal`),
  CONSTRAINT `fk_compra_condicion` FOREIGN KEY (`id_condicion_venta`) REFERENCES `condicion_venta` (`id_condicion_venta`) ON UPDATE CASCADE,
  CONSTRAINT `fk_compra_estado` FOREIGN KEY (`id_estado_compra`) REFERENCES `estado_compra` (`id_estado_compra`) ON UPDATE CASCADE,
  CONSTRAINT `fk_compra_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedor` (`id_proveedor`) ON UPDATE CASCADE,
  CONSTRAINT `fk_compra_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursal` (`id_sucursal`),
  CONSTRAINT `fk_compra_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compra`
--

LOCK TABLES `compra` WRITE;
/*!40000 ALTER TABLE `compra` DISABLE KEYS */;
INSERT INTO `compra` VALUES (3,3,1,1,2,1,'001-0001-0000001','2026-08-04 22:21:51',NULL),(4,4,1,1,2,1,'001-001-0004521','2026-08-08 13:03:21','Compra inicial de temporada'),(5,5,1,1,2,2,'002-001-0000988','2026-08-08 13:03:21','Reventa y descartables'),(6,4,1,1,2,1,'001-001-0004522','2026-08-08 13:03:21',NULL);
/*!40000 ALTER TABLE `compra` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compra_cuota`
--

DROP TABLE IF EXISTS `compra_cuota`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compra_cuota` (
  `id_compra_cuota` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_compra` int(10) unsigned NOT NULL,
  `nro_cuota` smallint(5) unsigned NOT NULL,
  `fecha_vencimiento` date NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  PRIMARY KEY (`id_compra_cuota`),
  UNIQUE KEY `uq_compra_cuota` (`id_compra`,`nro_cuota`),
  KEY `idx_cuota_vencimiento` (`fecha_vencimiento`),
  CONSTRAINT `fk_cuota_compra` FOREIGN KEY (`id_compra`) REFERENCES `compra` (`id_compra`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_cuota_nro` CHECK (`nro_cuota` > 0),
  CONSTRAINT `chk_cuota_monto` CHECK (`monto` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compra_cuota`
--

LOCK TABLES `compra_cuota` WRITE;
/*!40000 ALTER TABLE `compra_cuota` DISABLE KEYS */;
/*!40000 ALTER TABLE `compra_cuota` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `condicion_venta`
--

DROP TABLE IF EXISTS `condicion_venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `condicion_venta` (
  `id_condicion_venta` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  `dias_credito` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_condicion_venta`),
  UNIQUE KEY `uq_condicion_nombre` (`nombre`),
  CONSTRAINT `chk_condicion_dias` CHECK (`dias_credito` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `condicion_venta`
--

LOCK TABLES `condicion_venta` WRITE;
/*!40000 ALTER TABLE `condicion_venta` DISABLE KEYS */;
INSERT INTO `condicion_venta` VALUES (1,'Contado',0,1),(2,'Credito',30,1);
/*!40000 ALTER TABLE `condicion_venta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuracion`
--

DROP TABLE IF EXISTS `configuracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracion` (
  `id_configuracion` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `nombre_salon` varchar(60) NOT NULL DEFAULT 'Peluquería Luque',
  `logo` varchar(120) DEFAULT NULL,
  `puntos_cada_gs` int(10) unsigned NOT NULL DEFAULT 10000,
  PRIMARY KEY (`id_configuracion`),
  CONSTRAINT `chk_config_unica` CHECK (`id_configuracion` = 1),
  CONSTRAINT `chk_config_puntos` CHECK (`puntos_cada_gs` between 100 and 10000000),
  CONSTRAINT `chk_config_nombre` CHECK (char_length(trim(`nombre_salon`)) >= 2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracion`
--

LOCK TABLES `configuracion` WRITE;
/*!40000 ALTER TABLE `configuracion` DISABLE KEYS */;
INSERT INTO `configuracion` VALUES (1,'Peluquería Luque',NULL,10000);
/*!40000 ALTER TABLE `configuracion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `contacto_soporte`
--

DROP TABLE IF EXISTS `contacto_soporte`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `contacto_soporte` (
  `id_contacto` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `canal` varchar(20) NOT NULL,
  `valor` varchar(160) NOT NULL,
  `etiqueta` varchar(40) DEFAULT NULL,
  `orden` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_contacto`),
  KEY `idx_contsop_orden` (`orden`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacto_soporte`
--

LOCK TABLES `contacto_soporte` WRITE;
/*!40000 ALTER TABLE `contacto_soporte` DISABLE KEYS */;
INSERT INTO `contacto_soporte` VALUES (1,'TELEGRAM','@peluqueriluque',NULL,0),(2,'WHATSAPP','0981 123 456',NULL,1);
/*!40000 ALTER TABLE `contacto_soporte` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `credencial_webauthn`
--

DROP TABLE IF EXISTS `credencial_webauthn`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `credencial_webauthn` (
  `id_credencial` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `credential_id` varchar(255) NOT NULL,
  `public_key` text NOT NULL,
  `contador` int(10) unsigned NOT NULL DEFAULT 0,
  `etiqueta` varchar(80) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_credencial`),
  UNIQUE KEY `uq_webauthn_credid` (`credential_id`),
  KEY `idx_webauthn_usuario` (`id_usuario`),
  CONSTRAINT `fk_webauthn_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `credencial_webauthn`
--

LOCK TABLES `credencial_webauthn` WRITE;
/*!40000 ALTER TABLE `credencial_webauthn` DISABLE KEYS */;
/*!40000 ALTER TABLE `credencial_webauthn` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `descuento`
--

DROP TABLE IF EXISTS `descuento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `descuento` (
  `id_descuento` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `descripcion` varchar(300) DEFAULT NULL,
  `tipo` varchar(20) NOT NULL,
  `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_descuento`),
  UNIQUE KEY `uq_descuento_nombre` (`nombre`),
  CONSTRAINT `chk_descuento_tipo` CHECK (`tipo` in ('PORCENTAJE','MONTO')),
  CONSTRAINT `chk_descuento_valor` CHECK (`valor` >= 0),
  CONSTRAINT `chk_descuento_fechas` CHECK (`fecha_fin` is null or `fecha_inicio` is null or `fecha_fin` >= `fecha_inicio`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `descuento`
--

LOCK TABLES `descuento` WRITE;
/*!40000 ALTER TABLE `descuento` DISABLE KEYS */;
INSERT INTO `descuento` VALUES (1,'Nivel Plata','Descuento por nivel de fidelizacion Plata','PORCENTAJE',5.00,NULL,NULL,1),(2,'Nivel Oro','Descuento por nivel de fidelizacion Oro','PORCENTAJE',10.00,NULL,NULL,1),(3,'Nivel Platino','Descuento por nivel de fidelizacion Platino','PORCENTAJE',15.00,NULL,NULL,1),(5,'Promo Invierno vigente','Promo Invierno vigente','PORCENTAJE',20.00,'2026-08-01','2026-08-31',1),(6,'Promo vencida','Promo vencida','PORCENTAJE',30.00,'2026-06-01','2026-06-30',1),(7,'Promo futura','Promo futura','MONTO',50000.00,'2026-12-01','2026-12-31',1);
/*!40000 ALTER TABLE `descuento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_compra`
--

DROP TABLE IF EXISTS `detalle_compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_compra` (
  `id_detalle_compra` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_compra` int(10) unsigned NOT NULL,
  `id_producto` int(10) unsigned NOT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 0.00,
  `precio_unitario` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_detalle_compra`),
  KEY `idx_dc_compra` (`id_compra`),
  KEY `idx_dc_producto` (`id_producto`),
  CONSTRAINT `fk_dc_compra` FOREIGN KEY (`id_compra`) REFERENCES `compra` (`id_compra`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dc_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`) ON UPDATE CASCADE,
  CONSTRAINT `chk_dc_cantidad` CHECK (`cantidad` > 0),
  CONSTRAINT `chk_dc_precio` CHECK (`precio_unitario` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_compra`
--

LOCK TABLES `detalle_compra` WRITE;
/*!40000 ALTER TABLE `detalle_compra` DISABLE KEYS */;
INSERT INTO `detalle_compra` VALUES (3,3,3,1.00,50000.00),(4,3,4,2.00,10000.00),(5,4,5,12.00,85000.00),(6,4,6,10.00,90000.00),(7,4,7,40.00,45000.00),(8,4,8,8.00,30000.00),(9,5,9,6.00,25000.00),(10,5,10,12.00,60000.00),(11,6,11,2.00,85000.00);
/*!40000 ALTER TABLE `detalle_compra` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_factura`
--

DROP TABLE IF EXISTS `detalle_factura`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_factura` (
  `id_detalle_factura` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_factura` int(10) unsigned NOT NULL,
  `id_servicio` int(10) unsigned DEFAULT NULL,
  `id_producto` int(10) unsigned DEFAULT NULL,
  `cantidad` decimal(10,2) NOT NULL DEFAULT 1.00,
  `precio_unitario` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tasa_iva` tinyint(3) unsigned NOT NULL DEFAULT 10,
  PRIMARY KEY (`id_detalle_factura`),
  KEY `idx_df_factura` (`id_factura`),
  KEY `idx_df_servicio` (`id_servicio`),
  KEY `idx_df_producto` (`id_producto`),
  CONSTRAINT `fk_df_factura` FOREIGN KEY (`id_factura`) REFERENCES `factura` (`id_factura`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_df_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`) ON UPDATE CASCADE,
  CONSTRAINT `fk_df_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicio` (`id_servicio`) ON UPDATE CASCADE,
  CONSTRAINT `chk_df_cantidad` CHECK (`cantidad` > 0),
  CONSTRAINT `chk_df_precio` CHECK (`precio_unitario` >= 0),
  CONSTRAINT `chk_df_iva` CHECK (`tasa_iva` in (0,5,10)),
  CONSTRAINT `chk_df_item` CHECK (`id_servicio` is not null and `id_producto` is null or `id_servicio` is null and `id_producto` is not null)
) ENGINE=InnoDB AUTO_INCREMENT=205 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_factura`
--

LOCK TABLES `detalle_factura` WRITE;
/*!40000 ALTER TABLE `detalle_factura` DISABLE KEYS */;
INSERT INTO `detalle_factura` VALUES (2,2,10,NULL,1.00,60000.00,10),(3,2,12,NULL,1.00,55000.00,10),(5,3,5,NULL,1.00,40000.00,10),(6,3,10,NULL,1.00,60000.00,10),(8,4,4,NULL,1.00,50000.00,10),(9,4,13,NULL,1.00,65000.00,10),(11,5,5,NULL,1.00,40000.00,10),(12,6,13,NULL,1.00,65000.00,10),(13,6,14,NULL,1.00,25000.00,10),(15,7,5,NULL,1.00,40000.00,10),(16,7,11,NULL,1.00,180000.00,10),(18,8,3,NULL,1.00,75000.00,10),(19,8,5,NULL,1.00,40000.00,10),(20,8,7,NULL,1.00,350000.00,10),(21,9,8,NULL,1.00,150000.00,10),(22,10,4,NULL,1.00,50000.00,10),(23,11,4,NULL,1.00,50000.00,10),(24,12,4,NULL,1.00,50000.00,10),(25,12,6,NULL,1.00,280000.00,10),(27,13,4,NULL,1.00,50000.00,10),(28,14,4,NULL,1.00,50000.00,10),(29,14,13,NULL,1.00,65000.00,10),(31,15,3,NULL,1.00,75000.00,10),(32,16,5,NULL,1.00,40000.00,10),(33,17,3,NULL,1.00,75000.00,10),(34,18,10,NULL,1.00,60000.00,10),(35,18,13,NULL,1.00,65000.00,10),(37,19,3,NULL,1.00,75000.00,10),(38,20,7,NULL,1.00,350000.00,10),(39,21,8,NULL,1.00,150000.00,10),(40,21,12,NULL,1.00,55000.00,10),(42,22,14,NULL,1.00,25000.00,10),(43,23,4,NULL,1.00,50000.00,10),(44,23,12,NULL,1.00,55000.00,10),(46,24,7,NULL,1.00,350000.00,10),(47,25,3,NULL,1.00,75000.00,10),(48,25,10,NULL,1.00,60000.00,10),(50,26,4,NULL,1.00,50000.00,10),(51,26,10,NULL,1.00,60000.00,10),(53,27,3,NULL,1.00,75000.00,10),(54,27,10,NULL,1.00,60000.00,10),(56,28,10,NULL,1.00,60000.00,10),(57,28,13,NULL,1.00,65000.00,10),(59,29,9,NULL,1.00,420000.00,10),(60,30,9,NULL,1.00,420000.00,10),(61,30,12,NULL,1.00,55000.00,10),(63,31,5,NULL,1.00,40000.00,10),(64,31,10,NULL,1.00,60000.00,10),(66,32,6,NULL,1.00,280000.00,10),(67,33,9,NULL,1.00,420000.00,10),(68,34,13,NULL,1.00,65000.00,10),(69,34,14,NULL,1.00,25000.00,10),(71,35,6,NULL,1.00,280000.00,10),(72,36,5,NULL,1.00,40000.00,10),(73,36,12,NULL,1.00,55000.00,10),(75,37,14,NULL,1.00,25000.00,10),(76,38,6,NULL,1.00,280000.00,10),(77,39,12,NULL,1.00,55000.00,10),(78,40,12,NULL,1.00,55000.00,10),(79,41,4,NULL,1.00,50000.00,10),(80,41,9,NULL,1.00,420000.00,10),(82,42,5,NULL,1.00,40000.00,10),(83,42,7,NULL,1.00,350000.00,10),(85,43,14,NULL,1.00,25000.00,10),(86,44,5,NULL,1.00,40000.00,10),(87,45,6,NULL,1.00,280000.00,10),(88,45,12,NULL,1.00,55000.00,10),(90,46,8,NULL,1.00,150000.00,10),(91,46,9,NULL,1.00,420000.00,10),(93,47,3,NULL,1.00,75000.00,10),(94,47,14,NULL,1.00,25000.00,10),(96,48,5,NULL,1.00,40000.00,10),(97,49,12,NULL,1.00,55000.00,10),(98,50,4,NULL,1.00,50000.00,10),(99,50,14,NULL,1.00,25000.00,10),(101,51,10,NULL,1.00,60000.00,10),(102,52,7,NULL,1.00,350000.00,10),(103,53,10,NULL,1.00,60000.00,10),(104,54,10,NULL,1.00,60000.00,10),(105,55,6,NULL,1.00,280000.00,10),(106,56,5,NULL,1.00,40000.00,10),(107,57,11,NULL,1.00,180000.00,10),(108,58,5,NULL,1.00,40000.00,10),(109,59,3,NULL,1.00,75000.00,10),(110,59,5,NULL,1.00,40000.00,10),(112,60,4,NULL,1.00,50000.00,10),(113,61,11,NULL,1.00,180000.00,10),(114,62,4,NULL,1.00,50000.00,10),(115,63,11,NULL,1.00,180000.00,10);
/*!40000 ALTER TABLE `detalle_factura` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_detfactura_ai
AFTER INSERT ON detalle_factura FOR EACH ROW
BEGIN
  DECLARE v_signo   TINYINT;
  DECLARE v_usuario INT UNSIGNED;

  IF NEW.id_producto IS NOT NULL THEN
    SELECT tc.signo, f.id_usuario INTO v_signo, v_usuario
    FROM factura f
    JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
    WHERE f.id_factura = NEW.id_factura;

    IF v_signo = 1 THEN
      INSERT INTO movimiento_inventario (id_producto, id_usuario, id_tipo_movimiento, cantidad, precio_unitario, referencia, observaciones)
      VALUES (NEW.id_producto, v_usuario, 7, NEW.cantidad, NEW.precio_unitario,
              CONCAT('FAC#', NEW.id_factura), 'Venta de producto facturada');
    ELSEIF v_signo = -1 THEN
      INSERT INTO movimiento_inventario (id_producto, id_usuario, id_tipo_movimiento, cantidad, precio_unitario, referencia, observaciones)
      VALUES (NEW.id_producto, v_usuario, 6, NEW.cantidad, NEW.precio_unitario,
              CONCAT('NC#', NEW.id_factura), 'Devolucion por nota de credito');
    END IF;
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `detalle_pago_personal`
--

DROP TABLE IF EXISTS `detalle_pago_personal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_pago_personal` (
  `id_detalle_pago` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_pago_personal` int(10) unsigned NOT NULL,
  `id_servicio_realizado` int(10) unsigned NOT NULL,
  `monto` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_detalle_pago`),
  UNIQUE KEY `uq_detalle_pago` (`id_servicio_realizado`),
  KEY `idx_dpp_pago` (`id_pago_personal`),
  CONSTRAINT `fk_dpp_pago` FOREIGN KEY (`id_pago_personal`) REFERENCES `pago_personal` (`id_pago_personal`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_dpp_servicio_realizado` FOREIGN KEY (`id_servicio_realizado`) REFERENCES `servicio_realizado` (`id_servicio_realizado`) ON UPDATE CASCADE,
  CONSTRAINT `chk_dpp_monto` CHECK (`monto` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=2068 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_pago_personal`
--

LOCK TABLES `detalle_pago_personal` WRITE;
/*!40000 ALTER TABLE `detalle_pago_personal` DISABLE KEYS */;
/*!40000 ALTER TABLE `detalle_pago_personal` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_pago_proveedor`
--

DROP TABLE IF EXISTS `detalle_pago_proveedor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_pago_proveedor` (
  `id_detalle_pago_proveedor` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_pago_proveedor` int(10) unsigned NOT NULL,
  `id_compra` int(10) unsigned NOT NULL,
  `monto_aplicado` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_detalle_pago_proveedor`),
  UNIQUE KEY `uq_dpprov` (`id_pago_proveedor`,`id_compra`),
  KEY `idx_dpprov_compra` (`id_compra`),
  CONSTRAINT `fk_dpprov_compra` FOREIGN KEY (`id_compra`) REFERENCES `compra` (`id_compra`) ON UPDATE CASCADE,
  CONSTRAINT `fk_dpprov_pago` FOREIGN KEY (`id_pago_proveedor`) REFERENCES `pago_proveedor` (`id_pago_proveedor`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_dpprov_monto` CHECK (`monto_aplicado` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_pago_proveedor`
--

LOCK TABLES `detalle_pago_proveedor` WRITE;
/*!40000 ALTER TABLE `detalle_pago_proveedor` DISABLE KEYS */;
INSERT INTO `detalle_pago_proveedor` VALUES (2,2,3,70000.00),(3,3,4,1584000.00);
/*!40000 ALTER TABLE `detalle_pago_proveedor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estado_caja`
--

DROP TABLE IF EXISTS `estado_caja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estado_caja` (
  `id_estado_caja` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL,
  PRIMARY KEY (`id_estado_caja`),
  UNIQUE KEY `uq_estado_caja_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estado_caja`
--

LOCK TABLES `estado_caja` WRITE;
/*!40000 ALTER TABLE `estado_caja` DISABLE KEYS */;
INSERT INTO `estado_caja` VALUES (1,'Abierta'),(2,'Cerrada');
/*!40000 ALTER TABLE `estado_caja` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estado_cita`
--

DROP TABLE IF EXISTS `estado_cita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estado_cita` (
  `id_estado_cita` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  `bloquea_agenda` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_estado_cita`),
  UNIQUE KEY `uq_estado_cita_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estado_cita`
--

LOCK TABLES `estado_cita` WRITE;
/*!40000 ALTER TABLE `estado_cita` DISABLE KEYS */;
INSERT INTO `estado_cita` VALUES (1,'Programada',1),(2,'Reprogramada',1),(3,'Cancelada',0),(4,'Atendida',0),(5,'En proceso',1),(6,'Ausente',0),(7,'Atrasada',1);
/*!40000 ALTER TABLE `estado_cita` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estado_cobro`
--

DROP TABLE IF EXISTS `estado_cobro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estado_cobro` (
  `id_estado_cobro` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  PRIMARY KEY (`id_estado_cobro`),
  UNIQUE KEY `uq_estado_cobro_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estado_cobro`
--

LOCK TABLES `estado_cobro` WRITE;
/*!40000 ALTER TABLE `estado_cobro` DISABLE KEYS */;
INSERT INTO `estado_cobro` VALUES (3,'Anulado'),(2,'Pendiente'),(1,'Registrado');
/*!40000 ALTER TABLE `estado_cobro` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estado_compra`
--

DROP TABLE IF EXISTS `estado_compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estado_compra` (
  `id_estado_compra` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  PRIMARY KEY (`id_estado_compra`),
  UNIQUE KEY `uq_estado_compra_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estado_compra`
--

LOCK TABLES `estado_compra` WRITE;
/*!40000 ALTER TABLE `estado_compra` DISABLE KEYS */;
INSERT INTO `estado_compra` VALUES (3,'Anulada'),(2,'Confirmada'),(1,'Pendiente');
/*!40000 ALTER TABLE `estado_compra` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estado_factura`
--

DROP TABLE IF EXISTS `estado_factura`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estado_factura` (
  `id_estado_factura` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  PRIMARY KEY (`id_estado_factura`),
  UNIQUE KEY `uq_estado_factura_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estado_factura`
--

LOCK TABLES `estado_factura` WRITE;
/*!40000 ALTER TABLE `estado_factura` DISABLE KEYS */;
INSERT INTO `estado_factura` VALUES (2,'Anulada'),(1,'Emitida');
/*!40000 ALTER TABLE `estado_factura` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estado_pago_personal`
--

DROP TABLE IF EXISTS `estado_pago_personal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estado_pago_personal` (
  `id_estado_pago` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  PRIMARY KEY (`id_estado_pago`),
  UNIQUE KEY `uq_estado_pago_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estado_pago_personal`
--

LOCK TABLES `estado_pago_personal` WRITE;
/*!40000 ALTER TABLE `estado_pago_personal` DISABLE KEYS */;
INSERT INTO `estado_pago_personal` VALUES (3,'Anulado'),(2,'Pendiente'),(1,'Registrado'),(4,'Revertido');
/*!40000 ALTER TABLE `estado_pago_personal` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estado_pago_proveedor`
--

DROP TABLE IF EXISTS `estado_pago_proveedor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estado_pago_proveedor` (
  `id_estado_pago_proveedor` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  PRIMARY KEY (`id_estado_pago_proveedor`),
  UNIQUE KEY `uq_estado_pago_prov_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estado_pago_proveedor`
--

LOCK TABLES `estado_pago_proveedor` WRITE;
/*!40000 ALTER TABLE `estado_pago_proveedor` DISABLE KEYS */;
INSERT INTO `estado_pago_proveedor` VALUES (2,'Anulado'),(1,'Registrado');
/*!40000 ALTER TABLE `estado_pago_proveedor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `factura`
--

DROP TABLE IF EXISTS `factura`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factura` (
  `id_factura` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `id_cita` int(10) unsigned DEFAULT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_tipo_comprobante` int(10) unsigned NOT NULL,
  `id_condicion_venta` int(10) unsigned NOT NULL,
  `id_timbrado` int(10) unsigned NOT NULL,
  `id_estado_factura` int(10) unsigned NOT NULL,
  `id_factura_origen` int(10) unsigned DEFAULT NULL,
  `nro_correlativo` int(10) unsigned NOT NULL,
  `fecha_emision` datetime NOT NULL DEFAULT current_timestamp(),
  `observaciones` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`id_factura`),
  UNIQUE KEY `uq_factura_nro` (`id_timbrado`,`nro_correlativo`),
  KEY `idx_factura_cliente` (`id_cliente`),
  KEY `idx_factura_cita` (`id_cita`),
  KEY `idx_factura_usuario` (`id_usuario`),
  KEY `idx_factura_tipo` (`id_tipo_comprobante`),
  KEY `idx_factura_condicion` (`id_condicion_venta`),
  KEY `idx_factura_estado` (`id_estado_factura`),
  KEY `idx_factura_origen` (`id_factura_origen`),
  KEY `idx_factura_fecha` (`fecha_emision`),
  CONSTRAINT `fk_factura_cita` FOREIGN KEY (`id_cita`) REFERENCES `cita` (`id_cita`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_factura_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`) ON UPDATE CASCADE,
  CONSTRAINT `fk_factura_condicion` FOREIGN KEY (`id_condicion_venta`) REFERENCES `condicion_venta` (`id_condicion_venta`) ON UPDATE CASCADE,
  CONSTRAINT `fk_factura_estado` FOREIGN KEY (`id_estado_factura`) REFERENCES `estado_factura` (`id_estado_factura`) ON UPDATE CASCADE,
  CONSTRAINT `fk_factura_origen` FOREIGN KEY (`id_factura_origen`) REFERENCES `factura` (`id_factura`) ON UPDATE CASCADE,
  CONSTRAINT `fk_factura_timbrado` FOREIGN KEY (`id_timbrado`) REFERENCES `timbrado` (`id_timbrado`) ON UPDATE CASCADE,
  CONSTRAINT `fk_factura_tipo` FOREIGN KEY (`id_tipo_comprobante`) REFERENCES `tipo_comprobante` (`id_tipo_comprobante`) ON UPDATE CASCADE,
  CONSTRAINT `fk_factura_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `chk_factura_correlativo` CHECK (`nro_correlativo` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=94 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `factura`
--

LOCK TABLES `factura` WRITE;
/*!40000 ALTER TABLE `factura` DISABLE KEYS */;
INSERT INTO `factura` VALUES (2,7,4,12,1,1,1,1,NULL,1,'2026-08-08 13:09:03',NULL),(3,8,6,12,1,1,1,1,NULL,2,'2026-08-08 13:09:03',NULL),(4,9,8,12,1,1,1,1,NULL,3,'2026-08-08 13:09:03',NULL),(5,7,9,12,1,1,1,1,NULL,4,'2026-08-08 13:09:03',NULL),(6,5,10,12,1,1,1,1,NULL,5,'2026-08-08 13:09:03',NULL),(7,9,3,12,1,1,1,1,NULL,6,'2026-08-08 13:09:04',NULL),(8,27,5,12,1,1,1,1,NULL,7,'2026-08-08 13:09:04',NULL),(9,5,7,12,1,1,1,1,NULL,8,'2026-08-08 13:09:04',NULL),(10,10,97,12,1,1,1,1,NULL,9,'2026-07-10 13:11:50',NULL),(11,12,98,12,1,1,1,1,NULL,10,'2026-07-10 13:11:51',NULL),(12,7,99,12,1,1,1,1,NULL,11,'2026-07-10 13:11:51',NULL),(13,8,100,12,1,1,1,1,NULL,12,'2026-07-10 13:11:52',NULL),(14,5,102,12,1,1,1,1,NULL,13,'2026-07-11 13:11:53',NULL),(15,1,103,12,1,1,1,1,NULL,14,'2026-07-11 13:11:54',NULL),(16,20,105,12,1,1,1,1,NULL,15,'2026-07-13 13:11:55',NULL),(17,34,107,12,1,1,1,1,NULL,16,'2026-07-14 13:11:56',NULL),(18,7,108,12,1,1,1,1,NULL,17,'2026-07-14 13:11:57',NULL),(19,9,111,12,1,1,1,1,NULL,18,'2026-07-15 13:11:58',NULL),(20,12,112,12,1,1,1,1,NULL,19,'2026-07-16 13:11:59',NULL),(21,1,113,12,1,1,1,1,NULL,20,'2026-07-16 13:11:59',NULL),(22,7,114,12,1,1,1,1,NULL,21,'2026-07-16 13:12:00',NULL),(23,11,115,12,1,1,1,1,NULL,22,'2026-07-17 13:12:01',NULL),(24,9,118,12,1,1,1,1,NULL,23,'2026-07-18 13:12:03',NULL),(25,9,120,12,1,1,1,1,NULL,24,'2026-07-18 13:12:04',NULL),(26,10,121,12,1,1,1,1,NULL,25,'2026-07-20 13:12:04',NULL),(27,20,122,12,1,1,1,1,NULL,26,'2026-07-20 13:12:05',NULL),(28,12,123,12,1,1,1,1,NULL,27,'2026-07-21 13:12:06',NULL),(29,12,124,12,1,1,1,1,NULL,28,'2026-07-21 13:12:07',NULL),(30,9,125,12,1,1,1,1,NULL,29,'2026-07-22 13:12:08',NULL),(31,12,126,12,1,1,1,1,NULL,30,'2026-07-22 13:12:08',NULL),(32,8,127,12,1,1,1,1,NULL,31,'2026-07-22 13:12:09',NULL),(33,8,128,12,1,1,1,1,NULL,32,'2026-07-23 13:12:10',NULL),(34,1,129,12,1,1,1,1,NULL,33,'2026-07-23 13:12:10',NULL),(35,33,130,12,1,1,1,1,NULL,34,'2026-07-23 13:12:11',NULL),(36,12,131,12,1,1,1,1,NULL,35,'2026-07-24 13:12:12',NULL),(37,10,132,12,1,1,1,1,NULL,36,'2026-07-24 13:12:12',NULL),(38,34,134,12,1,1,1,1,NULL,37,'2026-07-25 13:12:14',NULL),(39,11,135,12,1,1,1,1,NULL,38,'2026-07-25 13:12:14',NULL),(40,17,136,12,1,1,1,1,NULL,39,'2026-07-25 13:12:15',NULL),(41,1,137,12,1,1,1,1,NULL,40,'2026-07-27 13:12:16',NULL),(42,23,140,12,1,1,1,1,NULL,41,'2026-07-28 13:12:17',NULL),(43,8,141,12,1,1,1,1,NULL,42,'2026-07-28 13:12:18',NULL),(44,10,143,12,1,1,1,1,NULL,43,'2026-07-29 13:12:19',NULL),(45,12,144,12,1,1,1,1,NULL,44,'2026-07-29 13:12:20',NULL),(46,12,146,12,1,1,1,1,NULL,45,'2026-07-30 13:12:21',NULL),(47,15,147,12,1,1,1,1,NULL,46,'2026-07-30 13:12:22',NULL),(48,5,148,12,1,1,1,1,NULL,47,'2026-07-30 13:12:22',NULL),(49,9,149,12,1,1,1,1,NULL,48,'2026-07-31 13:12:23',NULL),(50,12,151,12,1,1,1,1,NULL,49,'2026-07-31 13:12:24',NULL),(51,22,152,12,1,1,1,1,NULL,50,'2026-08-01 13:12:25',NULL),(52,20,153,12,1,1,1,1,NULL,51,'2026-08-01 13:12:26',NULL),(53,25,154,12,1,1,1,1,NULL,52,'2026-08-01 13:12:26',NULL),(54,5,156,12,1,1,1,1,NULL,53,'2026-08-01 13:12:27',NULL),(55,11,160,12,1,1,1,1,NULL,54,'2026-08-04 13:12:29',NULL),(56,1,161,12,1,1,1,1,NULL,55,'2026-08-04 13:12:30',NULL),(57,10,163,12,1,1,1,1,NULL,56,'2026-08-05 13:12:31',NULL),(58,13,164,12,1,1,1,1,NULL,57,'2026-08-06 13:12:32',NULL),(59,12,165,12,1,1,1,1,NULL,58,'2026-08-06 13:12:33',NULL),(60,5,169,12,1,1,1,1,NULL,59,'2026-08-07 13:12:35',NULL),(61,1,171,12,1,1,1,2,NULL,60,'2026-08-07 13:12:35',NULL),(62,5,NULL,1,5,1,2,1,60,1,'2026-08-08 13:17:46','Devolución del cliente'),(63,1,171,1,1,1,1,1,NULL,61,'2026-08-08 13:23:14',NULL);
/*!40000 ALTER TABLE `factura` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_factura_bi
BEFORE INSERT ON factura FOR EACH ROW
BEGIN
  DECLARE v_tipo_tim INT UNSIGNED;
  DECLARE v_ini      DATE;
  DECLARE v_fin      DATE;
  DECLARE v_desde    INT UNSIGNED;
  DECLARE v_hasta    INT UNSIGNED;
  DECLARE v_activo   TINYINT(1);
  DECLARE v_req      TINYINT(1) DEFAULT 0;

  SELECT id_tipo_comprobante, fecha_inicio, fecha_fin, nro_desde, nro_hasta, activo
    INTO v_tipo_tim, v_ini, v_fin, v_desde, v_hasta, v_activo
  FROM timbrado WHERE id_timbrado = NEW.id_timbrado;

  IF COALESCE(v_activo, 0) <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El timbrado no existe o no esta activo.';
  END IF;

  IF v_tipo_tim <> NEW.id_tipo_comprobante THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El timbrado no corresponde a ese tipo de comprobante.';
  END IF;

  IF DATE(NEW.fecha_emision) < v_ini OR DATE(NEW.fecha_emision) > v_fin THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La fecha de emision esta fuera de la vigencia del timbrado.';
  END IF;

  IF NEW.nro_correlativo < v_desde OR NEW.nro_correlativo > v_hasta THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El correlativo esta fuera del rango del timbrado.';
  END IF;

  SELECT requiere_origen INTO v_req FROM tipo_comprobante
   WHERE id_tipo_comprobante = NEW.id_tipo_comprobante;

  IF v_req = 1 AND NEW.id_factura_origen IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Este tipo de comprobante necesita la factura de origen.';
  END IF;

  IF v_req = 0 AND NEW.id_factura_origen IS NOT NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Este tipo de comprobante no admite factura de origen.';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_factura_au
AFTER UPDATE ON factura FOR EACH ROW
BEGIN
  IF OLD.id_estado_factura <> 2 AND NEW.id_estado_factura = 2 THEN
    INSERT INTO auditoria (id_usuario, accion, modulo, tabla_afectada, id_registro, detalle)
    VALUES (COALESCE(@usuario_actual, NEW.id_usuario), 'ANULAR', 'Facturacion', 'factura', NEW.id_factura,
            CONCAT('Comprobante ', fn_factura_nro(NEW.id_factura), ' anulado.'));
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `factura_descuento`
--

DROP TABLE IF EXISTS `factura_descuento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factura_descuento` (
  `id_factura_descuento` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_factura` int(10) unsigned NOT NULL,
  `id_descuento` int(10) unsigned NOT NULL,
  `monto_aplicado` decimal(14,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_factura_descuento`),
  UNIQUE KEY `uq_factura_descuento` (`id_factura`,`id_descuento`),
  KEY `idx_fd_descuento` (`id_descuento`),
  CONSTRAINT `fk_fd_descuento` FOREIGN KEY (`id_descuento`) REFERENCES `descuento` (`id_descuento`) ON UPDATE CASCADE,
  CONSTRAINT `fk_fd_factura` FOREIGN KEY (`id_factura`) REFERENCES `factura` (`id_factura`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_fd_monto` CHECK (`monto_aplicado` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=32 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `factura_descuento`
--

LOCK TABLES `factura_descuento` WRITE;
/*!40000 ALTER TABLE `factura_descuento` DISABLE KEYS */;
INSERT INTO `factura_descuento` VALUES (1,59,1,5750.00);
/*!40000 ALTER TABLE `factura_descuento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `factura_electronica`
--

DROP TABLE IF EXISTS `factura_electronica`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `factura_electronica` (
  `id_factura_electronica` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_factura` int(10) unsigned NOT NULL,
  `cdc` char(44) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE',
  `track_id` varchar(40) DEFAULT NULL,
  `kude_url` varchar(300) DEFAULT NULL,
  `xml_url` varchar(300) DEFAULT NULL,
  `mensaje` varchar(500) DEFAULT NULL,
  `intentos` int(10) unsigned NOT NULL DEFAULT 0,
  `fecha_envio` datetime DEFAULT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_factura_electronica`),
  UNIQUE KEY `uq_fe_factura` (`id_factura`),
  KEY `ix_fe_estado` (`estado`),
  CONSTRAINT `fk_fe_factura` FOREIGN KEY (`id_factura`) REFERENCES `factura` (`id_factura`) ON DELETE CASCADE,
  CONSTRAINT `chk_fe_estado` CHECK (`estado` in ('PENDIENTE','ENVIADO','RECHAZADO')),
  CONSTRAINT `chk_fe_cdc` CHECK (`estado` <> 'ENVIADO' or `cdc` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `factura_electronica`
--

LOCK TABLES `factura_electronica` WRITE;
/*!40000 ALTER TABLE `factura_electronica` DISABLE KEYS */;
/*!40000 ALTER TABLE `factura_electronica` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `metodo_pago`
--

DROP TABLE IF EXISTS `metodo_pago`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `metodo_pago` (
  `id_metodo_pago` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  `tipo` varchar(10) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_metodo_pago`),
  UNIQUE KEY `uq_metodo_pago_nombre` (`nombre`),
  CONSTRAINT `chk_metodo_tipo` CHECK (`tipo` in ('EFECTIVO','TARJETA','BANCO','CHEQUE','OTRO'))
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `metodo_pago`
--

LOCK TABLES `metodo_pago` WRITE;
/*!40000 ALTER TABLE `metodo_pago` DISABLE KEYS */;
INSERT INTO `metodo_pago` VALUES (1,'Efectivo','EFECTIVO',1),(2,'Tarjeta de debito','TARJETA',1),(3,'Tarjeta de credito','TARJETA',1),(4,'Transferencia bancaria','BANCO',1),(5,'Cheque','CHEQUE',1),(6,'Billetera electronica','OTRO',1);
/*!40000 ALTER TABLE `metodo_pago` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movimiento_caja`
--

DROP TABLE IF EXISTS `movimiento_caja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movimiento_caja` (
  `id_movimiento_caja` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_caja` int(10) unsigned NOT NULL,
  `tipo` varchar(10) NOT NULL,
  `monto` decimal(14,2) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `concepto` varchar(150) DEFAULT NULL,
  PRIMARY KEY (`id_movimiento_caja`),
  KEY `idx_mc_caja` (`id_caja`),
  CONSTRAINT `fk_mc_caja` FOREIGN KEY (`id_caja`) REFERENCES `caja` (`id_caja`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_mc_tipo` CHECK (`tipo` in ('INGRESO','EGRESO')),
  CONSTRAINT `chk_mc_monto` CHECK (`monto` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimiento_caja`
--

LOCK TABLES `movimiento_caja` WRITE;
/*!40000 ALTER TABLE `movimiento_caja` DISABLE KEYS */;
/*!40000 ALTER TABLE `movimiento_caja` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `movimiento_inventario`
--

DROP TABLE IF EXISTS `movimiento_inventario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movimiento_inventario` (
  `id_movimiento` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_producto` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_tipo_movimiento` int(10) unsigned NOT NULL,
  `cantidad` decimal(12,4) NOT NULL,
  `precio_unitario` decimal(12,2) DEFAULT NULL,
  `referencia` varchar(40) DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `observaciones` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`id_movimiento`),
  KEY `idx_mi_producto` (`id_producto`,`fecha`),
  KEY `idx_mi_usuario` (`id_usuario`),
  KEY `idx_mi_tipo` (`id_tipo_movimiento`),
  KEY `idx_mi_referencia` (`referencia`),
  KEY `fk_movinv_sucursal` (`id_sucursal`),
  CONSTRAINT `fk_mi_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`) ON UPDATE CASCADE,
  CONSTRAINT `fk_mi_tipo` FOREIGN KEY (`id_tipo_movimiento`) REFERENCES `tipo_movimiento_inventario` (`id_tipo_movimiento`) ON UPDATE CASCADE,
  CONSTRAINT `fk_mi_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `fk_movinv_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursal` (`id_sucursal`),
  CONSTRAINT `chk_mi_cantidad` CHECK (`cantidad` > 0),
  CONSTRAINT `chk_mi_precio` CHECK (`precio_unitario` is null or `precio_unitario` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=416 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimiento_inventario`
--

LOCK TABLES `movimiento_inventario` WRITE;
/*!40000 ALTER TABLE `movimiento_inventario` DISABLE KEYS */;
INSERT INTO `movimiento_inventario` VALUES (5,3,1,1,1,1.0000,50000.00,'COM#3','2026-08-04 22:21:51','Entrada por compra confirmada'),(6,4,1,1,1,2.0000,10000.00,'COM#3','2026-08-04 22:21:51','Entrada por compra confirmada'),(7,5,1,1,1,12.0000,85000.00,'COM#4','2026-08-08 13:03:21','Entrada por compra confirmada'),(8,6,1,1,1,10.0000,90000.00,'COM#4','2026-08-08 13:03:21','Entrada por compra confirmada'),(9,7,1,1,1,40.0000,45000.00,'COM#4','2026-08-08 13:03:21','Entrada por compra confirmada'),(10,8,1,1,1,8.0000,30000.00,'COM#4','2026-08-08 13:03:21','Entrada por compra confirmada'),(14,9,1,1,1,6.0000,25000.00,'COM#5','2026-08-08 13:03:21','Entrada por compra confirmada'),(15,10,1,1,1,12.0000,60000.00,'COM#5','2026-08-08 13:03:21','Entrada por compra confirmada'),(17,11,1,1,1,2.0000,85000.00,'COM#6','2026-08-08 13:03:21','Entrada por compra confirmada'),(18,8,1,10,2,0.0400,NULL,'SR#2','2026-08-08 13:08:57','Consumo durante el servicio'),(19,8,1,8,2,0.0300,NULL,'SR#4','2026-08-08 13:08:58','Consumo durante el servicio'),(20,11,1,8,2,1.0000,NULL,'SR#4','2026-08-08 13:08:58','Consumo durante el servicio'),(21,6,1,8,2,0.0500,NULL,'SR#6','2026-08-08 13:08:58','Consumo durante el servicio'),(22,3,1,8,2,1.0000,NULL,'SR#6','2026-08-08 13:08:58','Consumo durante el servicio'),(23,5,1,10,2,0.0400,NULL,'SR#8','2026-08-08 13:08:59','Consumo durante el servicio'),(24,8,1,8,2,0.0400,NULL,'SR#9','2026-08-08 13:09:00','Consumo durante el servicio'),(25,6,1,11,2,0.0300,NULL,'SR#11','2026-08-08 13:09:01','Consumo durante el servicio'),(26,6,1,9,2,0.0400,NULL,'SR#13','2026-08-08 13:09:01','Consumo durante el servicio'),(27,5,1,9,2,0.0500,NULL,'SR#16','2026-08-08 13:09:02','Consumo durante el servicio'),(28,5,1,8,2,0.0500,NULL,'SR#17','2026-07-10 13:11:50','Consumo durante el servicio'),(29,8,1,10,2,0.0200,NULL,'SR#18','2026-07-10 13:11:51','Consumo durante el servicio'),(30,6,1,11,2,0.0500,NULL,'SR#19','2026-07-10 13:11:51','Consumo durante el servicio'),(31,4,1,11,2,1.0000,NULL,'SR#19','2026-07-10 13:11:51','Consumo durante el servicio'),(32,5,1,9,2,0.0200,NULL,'SR#21','2026-07-10 13:11:52','Consumo durante el servicio'),(33,7,1,9,2,1.0000,NULL,'SR#21','2026-07-10 13:11:52','Consumo durante el servicio'),(34,6,1,8,2,0.0300,NULL,'SR#22','2026-07-11 13:11:53','Consumo durante el servicio'),(35,8,1,10,2,0.0200,NULL,'SR#24','2026-07-11 13:11:54','Consumo durante el servicio'),(36,5,1,8,2,0.0500,NULL,'SR#25','2026-07-13 13:11:55','Consumo durante el servicio'),(37,11,1,8,2,1.0000,NULL,'SR#25','2026-07-13 13:11:55','Consumo durante el servicio'),(39,5,1,10,2,0.0200,NULL,'SR#28','2026-07-14 13:11:56','Consumo durante el servicio'),(40,4,1,10,2,1.0000,NULL,'SR#28','2026-07-14 13:11:56','Consumo durante el servicio'),(41,6,1,10,2,0.0300,NULL,'SR#29','2026-07-14 13:11:56','Consumo durante el servicio'),(43,6,1,9,2,0.0200,NULL,'SR#32','2026-07-15 13:11:58','Consumo durante el servicio'),(44,6,1,9,2,0.0400,NULL,'SR#33','2026-07-16 13:11:59','Consumo durante el servicio'),(45,9,1,9,2,1.0000,NULL,'SR#33','2026-07-16 13:11:59','Consumo durante el servicio'),(46,6,1,11,2,0.0300,NULL,'SR#34','2026-07-16 13:11:59','Consumo durante el servicio'),(47,10,1,11,2,1.0000,NULL,'SR#34','2026-07-16 13:11:59','Consumo durante el servicio'),(48,8,1,11,2,0.0300,NULL,'SR#36','2026-07-16 13:12:00','Consumo durante el servicio'),(49,8,1,10,2,0.0600,NULL,'SR#37','2026-07-17 13:12:01','Consumo durante el servicio'),(50,10,1,10,2,1.0000,NULL,'SR#37','2026-07-17 13:12:01','Consumo durante el servicio'),(53,6,1,11,2,0.0200,NULL,'SR#42','2026-07-18 13:12:03','Consumo durante el servicio'),(54,5,1,11,2,0.0200,NULL,'SR#43','2026-07-18 13:12:03','Consumo durante el servicio'),(55,10,1,11,2,1.0000,NULL,'SR#43','2026-07-18 13:12:03','Consumo durante el servicio'),(56,5,1,8,2,0.0500,NULL,'SR#45','2026-07-20 13:12:04','Consumo durante el servicio'),(57,10,1,8,2,1.0000,NULL,'SR#45','2026-07-20 13:12:04','Consumo durante el servicio'),(58,6,1,10,2,0.0400,NULL,'SR#47','2026-07-20 13:12:05','Consumo durante el servicio'),(59,5,1,10,2,0.0500,NULL,'SR#49','2026-07-21 13:12:06','Consumo durante el servicio'),(60,9,1,10,2,1.0000,NULL,'SR#49','2026-07-21 13:12:06','Consumo durante el servicio'),(61,5,1,9,2,0.0500,NULL,'SR#51','2026-07-21 13:12:06','Consumo durante el servicio'),(62,8,1,11,2,0.0200,NULL,'SR#52','2026-07-22 13:12:08','Consumo durante el servicio'),(63,6,1,10,2,0.0300,NULL,'SR#54','2026-07-22 13:12:08','Consumo durante el servicio'),(64,9,1,10,2,1.0000,NULL,'SR#54','2026-07-22 13:12:08','Consumo durante el servicio'),(65,6,1,11,2,0.0300,NULL,'SR#56','2026-07-22 13:12:08','Consumo durante el servicio'),(66,5,1,10,2,0.0500,NULL,'SR#57','2026-07-23 13:12:09','Consumo durante el servicio'),(67,9,1,10,2,1.0000,NULL,'SR#57','2026-07-23 13:12:09','Consumo durante el servicio'),(68,5,1,8,2,0.0400,NULL,'SR#58','2026-07-23 13:12:10','Consumo durante el servicio'),(69,5,1,9,2,0.0300,NULL,'SR#60','2026-07-23 13:12:11','Consumo durante el servicio'),(70,5,1,8,2,0.0400,NULL,'SR#61','2026-07-24 13:12:12','Consumo durante el servicio'),(71,8,1,10,2,0.0600,NULL,'SR#63','2026-07-24 13:12:12','Consumo durante el servicio'),(72,7,1,10,2,1.0000,NULL,'SR#63','2026-07-24 13:12:12','Consumo durante el servicio'),(74,5,1,9,2,0.0400,NULL,'SR#65','2026-07-25 13:12:14','Consumo durante el servicio'),(75,10,1,9,2,1.0000,NULL,'SR#65','2026-07-25 13:12:14','Consumo durante el servicio'),(76,6,1,10,2,0.0400,NULL,'SR#66','2026-07-25 13:12:14','Consumo durante el servicio'),(77,7,1,10,2,1.0000,NULL,'SR#66','2026-07-25 13:12:14','Consumo durante el servicio'),(78,5,1,11,2,0.0200,NULL,'SR#67','2026-07-25 13:12:15','Consumo durante el servicio'),(79,5,1,9,2,0.0500,NULL,'SR#68','2026-07-27 13:12:16','Consumo durante el servicio'),(81,6,1,9,2,0.0200,NULL,'SR#72','2026-07-28 13:12:17','Consumo durante el servicio'),(82,6,1,11,2,0.0300,NULL,'SR#74','2026-07-28 13:12:18','Consumo durante el servicio'),(83,6,1,9,2,0.0400,NULL,'SR#75','2026-07-29 13:12:19','Consumo durante el servicio'),(84,6,1,10,2,0.0400,NULL,'SR#76','2026-07-29 13:12:20','Consumo durante el servicio'),(86,5,1,11,2,0.0300,NULL,'SR#79','2026-07-30 13:12:21','Consumo durante el servicio'),(87,9,1,11,2,1.0000,NULL,'SR#79','2026-07-30 13:12:21','Consumo durante el servicio'),(88,6,1,9,2,0.0200,NULL,'SR#81','2026-07-30 13:12:21','Consumo durante el servicio'),(89,9,1,9,2,1.0000,NULL,'SR#81','2026-07-30 13:12:21','Consumo durante el servicio'),(90,8,1,10,2,0.0200,NULL,'SR#83','2026-07-30 13:12:22','Consumo durante el servicio'),(91,8,1,8,2,0.0300,NULL,'SR#84','2026-07-31 13:12:23','Consumo durante el servicio'),(93,5,1,11,2,0.0300,NULL,'SR#86','2026-07-31 13:12:24','Consumo durante el servicio'),(94,5,1,9,2,0.0400,NULL,'SR#88','2026-08-01 13:12:25','Consumo durante el servicio'),(95,7,1,9,2,1.0000,NULL,'SR#88','2026-08-01 13:12:25','Consumo durante el servicio'),(96,8,1,9,2,0.0400,NULL,'SR#89','2026-08-01 13:12:25','Consumo durante el servicio'),(97,8,1,10,2,0.0300,NULL,'SR#90','2026-08-01 13:12:26','Consumo durante el servicio'),(98,10,1,10,2,1.0000,NULL,'SR#90','2026-08-01 13:12:26','Consumo durante el servicio'),(100,8,1,9,2,0.0400,NULL,'SR#92','2026-08-01 13:12:27','Consumo durante el servicio'),(103,6,1,9,2,0.0400,NULL,'SR#96','2026-08-04 13:12:29','Consumo durante el servicio'),(104,6,1,10,2,0.0200,NULL,'SR#97','2026-08-04 13:12:30','Consumo durante el servicio'),(105,7,1,10,2,1.0000,NULL,'SR#97','2026-08-04 13:12:30','Consumo durante el servicio'),(107,8,1,8,2,0.0300,NULL,'SR#100','2026-08-05 13:12:31','Consumo durante el servicio'),(108,10,1,8,2,1.0000,NULL,'SR#100','2026-08-05 13:12:31','Consumo durante el servicio'),(109,6,1,8,2,0.0400,NULL,'SR#101','2026-08-06 13:12:32','Consumo durante el servicio'),(110,7,1,8,2,1.0000,NULL,'SR#101','2026-08-06 13:12:32','Consumo durante el servicio'),(111,6,1,11,2,0.0400,NULL,'SR#102','2026-08-06 13:12:33','Consumo durante el servicio'),(114,5,1,8,2,0.0300,NULL,'SR#107','2026-08-07 13:12:34','Consumo durante el servicio'),(116,8,1,10,2,0.0400,NULL,'SR#110','2026-08-07 13:12:35','Consumo durante el servicio');
/*!40000 ALTER TABLE `movimiento_inventario` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_movinv_bi BEFORE INSERT ON movimiento_inventario
FOR EACH ROW
BEGIN
  DECLARE v_signo CHAR(1);
  DECLARE v_stock DECIMAL(12,4) DEFAULT 0;
  DECLARE v_lock  INT UNSIGNED DEFAULT NULL;

  SELECT id_producto INTO v_lock FROM producto_sucursal
   WHERE id_producto = NEW.id_producto AND id_sucursal = NEW.id_sucursal FOR UPDATE;

  IF v_lock IS NULL THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Ese producto no esta habilitado en esa sucursal.';
  END IF;

  SELECT signo INTO v_signo FROM tipo_movimiento_inventario
   WHERE id_tipo_movimiento = NEW.id_tipo_movimiento;

  IF v_signo = 'S' THEN
    SELECT COALESCE(SUM(CASE WHEN t.signo = 'E' THEN m.cantidad ELSE -m.cantidad END), 0)
      INTO v_stock
    FROM movimiento_inventario m
    JOIN tipo_movimiento_inventario t ON t.id_tipo_movimiento = m.id_tipo_movimiento
    WHERE m.id_producto = NEW.id_producto
      AND m.id_sucursal = NEW.id_sucursal;

    IF v_stock - NEW.cantidad < 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No hay stock suficiente para esa salida.';
    END IF;
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_movinv_ai AFTER INSERT ON movimiento_inventario
FOR EACH ROW
BEGIN
  DECLARE v_stock  DECIMAL(12,4) DEFAULT 0;
  DECLARE v_minimo DECIMAL(10,2) DEFAULT 0;
  DECLARE v_nombre VARCHAR(100);
  DECLARE v_local  VARCHAR(100);
  DECLARE v_activo TINYINT(1) DEFAULT 0;

  SELECT p.nombre, ps.stock_minimo, ps.activo, su.nombre
    INTO v_nombre, v_minimo, v_activo, v_local
  FROM producto p
  JOIN producto_sucursal ps ON ps.id_producto = p.id_producto AND ps.id_sucursal = NEW.id_sucursal
  JOIN sucursal su ON su.id_sucursal = NEW.id_sucursal
  WHERE p.id_producto = NEW.id_producto AND p.activo = 1;

  SET v_stock = fn_producto_stock(NEW.id_producto, NEW.id_sucursal);

  IF v_activo = 1 AND v_stock <= v_minimo
     AND NOT EXISTS (SELECT 1 FROM notificacion
                      WHERE id_producto = NEW.id_producto
                        AND id_sucursal = NEW.id_sucursal
                        AND id_tipo_notificacion = 5
                        AND estado = 'PENDIENTE') THEN
    INSERT INTO notificacion (id_tipo_notificacion, id_producto, id_sucursal, canal, mensaje, estado)
    VALUES (5, NEW.id_producto, NEW.id_sucursal, 'SISTEMA',
            CONCAT('El producto ', v_nombre, ' quedo en ', v_stock,
                   ' (minimo ', v_minimo, ') en ', v_local, '. Conviene reponer.'),
            'PENDIENTE');
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `movimiento_punto`
--

DROP TABLE IF EXISTS `movimiento_punto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `movimiento_punto` (
  `id_movimiento_punto` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `id_factura` int(10) unsigned DEFAULT NULL,
  `tipo` varchar(10) NOT NULL,
  `puntos` int(11) NOT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `observaciones` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`id_movimiento_punto`),
  KEY `idx_mp_cliente` (`id_cliente`,`fecha`),
  KEY `idx_mp_factura` (`id_factura`),
  CONSTRAINT `fk_mp_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_mp_factura` FOREIGN KEY (`id_factura`) REFERENCES `factura` (`id_factura`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_mp_tipo` CHECK (`tipo` = 'ACUMULA' and `puntos` > 0 or `tipo` = 'CANJE' and `puntos` < 0 or `tipo` = 'AJUSTE' and `puntos` <> 0)
) ENGINE=InnoDB AUTO_INCREMENT=361 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimiento_punto`
--

LOCK TABLES `movimiento_punto` WRITE;
/*!40000 ALTER TABLE `movimiento_punto` DISABLE KEYS */;
INSERT INTO `movimiento_punto` VALUES (1,7,2,'ACUMULA',11,'2026-08-08 13:09:03','Emisión del comprobante 001-001-0000001'),(2,8,3,'ACUMULA',10,'2026-08-08 13:09:03','Emisión del comprobante 001-001-0000002'),(3,9,4,'ACUMULA',11,'2026-08-08 13:09:03','Emisión del comprobante 001-001-0000003'),(4,7,5,'ACUMULA',4,'2026-08-08 13:09:03','Emisión del comprobante 001-001-0000004'),(5,5,6,'ACUMULA',9,'2026-08-08 13:09:03','Emisión del comprobante 001-001-0000005'),(6,9,7,'ACUMULA',22,'2026-08-08 13:09:04','Emisión del comprobante 001-001-0000006'),(7,27,8,'ACUMULA',46,'2026-08-08 13:09:04','Emisión del comprobante 001-001-0000007'),(8,5,9,'ACUMULA',15,'2026-08-08 13:09:04','Emisión del comprobante 001-001-0000008'),(9,10,10,'ACUMULA',5,'2026-08-08 13:11:50','Emisión del comprobante 001-001-0000009'),(10,12,11,'ACUMULA',5,'2026-08-08 13:11:51','Emisión del comprobante 001-001-0000010'),(11,7,12,'ACUMULA',33,'2026-08-08 13:11:51','Emisión del comprobante 001-001-0000011'),(12,8,13,'ACUMULA',5,'2026-08-08 13:11:52','Emisión del comprobante 001-001-0000012'),(13,5,14,'ACUMULA',11,'2026-08-08 13:11:53','Emisión del comprobante 001-001-0000013'),(14,1,15,'ACUMULA',7,'2026-08-08 13:11:54','Emisión del comprobante 001-001-0000014'),(15,20,16,'ACUMULA',4,'2026-08-08 13:11:55','Emisión del comprobante 001-001-0000015'),(16,34,17,'ACUMULA',7,'2026-08-08 13:11:56','Emisión del comprobante 001-001-0000016'),(17,7,18,'ACUMULA',12,'2026-08-08 13:11:57','Emisión del comprobante 001-001-0000017'),(18,9,19,'ACUMULA',7,'2026-08-08 13:11:58','Emisión del comprobante 001-001-0000018'),(19,12,20,'ACUMULA',35,'2026-08-08 13:11:59','Emisión del comprobante 001-001-0000019'),(20,1,21,'ACUMULA',20,'2026-08-08 13:12:00','Emisión del comprobante 001-001-0000020'),(21,7,22,'ACUMULA',2,'2026-08-08 13:12:00','Emisión del comprobante 001-001-0000021'),(22,11,23,'ACUMULA',10,'2026-08-08 13:12:01','Emisión del comprobante 001-001-0000022'),(23,9,24,'ACUMULA',35,'2026-08-08 13:12:03','Emisión del comprobante 001-001-0000023'),(24,9,25,'ACUMULA',13,'2026-08-08 13:12:04','Emisión del comprobante 001-001-0000024'),(25,10,26,'ACUMULA',11,'2026-08-08 13:12:05','Emisión del comprobante 001-001-0000025'),(26,20,27,'ACUMULA',13,'2026-08-08 13:12:05','Emisión del comprobante 001-001-0000026'),(27,12,28,'ACUMULA',12,'2026-08-08 13:12:06','Emisión del comprobante 001-001-0000027'),(28,12,29,'ACUMULA',42,'2026-08-08 13:12:07','Emisión del comprobante 001-001-0000028'),(29,9,30,'ACUMULA',47,'2026-08-08 13:12:08','Emisión del comprobante 001-001-0000029'),(30,12,31,'ACUMULA',10,'2026-08-08 13:12:08','Emisión del comprobante 001-001-0000030'),(31,8,32,'ACUMULA',28,'2026-08-08 13:12:09','Emisión del comprobante 001-001-0000031'),(32,8,33,'ACUMULA',42,'2026-08-08 13:12:10','Emisión del comprobante 001-001-0000032'),(33,1,34,'ACUMULA',9,'2026-08-08 13:12:10','Emisión del comprobante 001-001-0000033'),(34,33,35,'ACUMULA',28,'2026-08-08 13:12:11','Emisión del comprobante 001-001-0000034'),(35,12,36,'ACUMULA',9,'2026-08-08 13:12:12','Emisión del comprobante 001-001-0000035'),(36,10,37,'ACUMULA',2,'2026-08-08 13:12:12','Emisión del comprobante 001-001-0000036'),(37,34,38,'ACUMULA',28,'2026-08-08 13:12:14','Emisión del comprobante 001-001-0000037'),(38,11,39,'ACUMULA',5,'2026-08-08 13:12:14','Emisión del comprobante 001-001-0000038'),(39,17,40,'ACUMULA',5,'2026-08-08 13:12:15','Emisión del comprobante 001-001-0000039'),(40,1,41,'ACUMULA',47,'2026-08-08 13:12:16','Emisión del comprobante 001-001-0000040'),(41,23,42,'ACUMULA',39,'2026-08-08 13:12:17','Emisión del comprobante 001-001-0000041'),(42,8,43,'ACUMULA',2,'2026-08-08 13:12:18','Emisión del comprobante 001-001-0000042'),(43,10,44,'ACUMULA',4,'2026-08-08 13:12:19','Emisión del comprobante 001-001-0000043'),(44,12,45,'ACUMULA',33,'2026-08-08 13:12:20','Emisión del comprobante 001-001-0000044'),(45,12,46,'ACUMULA',57,'2026-08-08 13:12:21','Emisión del comprobante 001-001-0000045'),(46,15,47,'ACUMULA',10,'2026-08-08 13:12:22','Emisión del comprobante 001-001-0000046'),(47,5,48,'ACUMULA',4,'2026-08-08 13:12:22','Emisión del comprobante 001-001-0000047'),(48,9,49,'ACUMULA',5,'2026-08-08 13:12:23','Emisión del comprobante 001-001-0000048'),(49,12,50,'ACUMULA',7,'2026-08-08 13:12:24','Emisión del comprobante 001-001-0000049'),(50,22,51,'ACUMULA',6,'2026-08-08 13:12:25','Emisión del comprobante 001-001-0000050'),(51,20,52,'ACUMULA',35,'2026-08-08 13:12:26','Emisión del comprobante 001-001-0000051'),(52,25,53,'ACUMULA',6,'2026-08-08 13:12:26','Emisión del comprobante 001-001-0000052'),(53,5,54,'ACUMULA',6,'2026-08-08 13:12:27','Emisión del comprobante 001-001-0000053'),(54,11,55,'ACUMULA',28,'2026-08-08 13:12:29','Emisión del comprobante 001-001-0000054'),(55,1,56,'ACUMULA',4,'2026-08-08 13:12:30','Emisión del comprobante 001-001-0000055'),(56,10,57,'ACUMULA',18,'2026-08-08 13:12:31','Emisión del comprobante 001-001-0000056'),(57,13,58,'ACUMULA',4,'2026-08-08 13:12:32','Emisión del comprobante 001-001-0000057'),(58,12,59,'ACUMULA',10,'2026-08-08 13:12:33','Emisión del comprobante 001-001-0000058'),(59,5,60,'ACUMULA',5,'2026-08-08 13:12:35','Emisión del comprobante 001-001-0000059'),(60,1,61,'ACUMULA',18,'2026-08-08 13:12:36','Emisión del comprobante 001-001-0000060'),(61,1,61,'AJUSTE',-18,'2026-08-08 13:17:46','Anulación del comprobante 001-001-0000060'),(62,5,60,'AJUSTE',-5,'2026-08-08 13:17:46','Nota de crédito del comprobante 001-001-0000059'),(63,1,63,'ACUMULA',18,'2026-08-08 13:23:14','Emisión del comprobante 001-001-0000061');
/*!40000 ALTER TABLE `movimiento_punto` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_movpunto_bi
BEFORE INSERT ON movimiento_punto FOR EACH ROW
BEGIN
  IF fn_cliente_puntos(NEW.id_cliente) + NEW.puntos < 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cliente no tiene tantos puntos para canjear.';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `nivel`
--

DROP TABLE IF EXISTS `nivel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nivel` (
  `id_nivel` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_descuento` int(10) unsigned DEFAULT NULL,
  `nombre` varchar(60) NOT NULL,
  `visitas_minimas` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_nivel`),
  UNIQUE KEY `uq_nivel_nombre` (`nombre`),
  KEY `idx_nivel_descuento` (`id_descuento`),
  CONSTRAINT `fk_nivel_descuento` FOREIGN KEY (`id_descuento`) REFERENCES `descuento` (`id_descuento`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `chk_nivel_visitas` CHECK (`visitas_minimas` >= 0)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `nivel`
--

LOCK TABLES `nivel` WRITE;
/*!40000 ALTER TABLE `nivel` DISABLE KEYS */;
INSERT INTO `nivel` VALUES (1,NULL,'Bronce',0,1),(2,1,'Plata',10,1),(3,2,'Oro',25,1),(4,3,'Platino',50,1);
/*!40000 ALTER TABLE `nivel` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificacion`
--

DROP TABLE IF EXISTS `notificacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificacion` (
  `id_notificacion` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_tipo_notificacion` int(10) unsigned NOT NULL,
  `id_cliente` int(10) unsigned DEFAULT NULL,
  `id_usuario` int(10) unsigned DEFAULT NULL,
  `id_cita` int(10) unsigned DEFAULT NULL,
  `id_producto` int(10) unsigned DEFAULT NULL,
  `id_sucursal` int(10) unsigned DEFAULT NULL,
  `canal` varchar(20) NOT NULL DEFAULT 'SISTEMA',
  `mensaje` varchar(300) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'PENDIENTE',
  `fecha_generacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_envio` datetime DEFAULT NULL,
  PRIMARY KEY (`id_notificacion`),
  KEY `idx_notif_tipo` (`id_tipo_notificacion`),
  KEY `idx_notif_cliente` (`id_cliente`),
  KEY `idx_notif_usuario` (`id_usuario`),
  KEY `idx_notif_cita` (`id_cita`),
  KEY `idx_notif_producto` (`id_producto`),
  KEY `idx_notif_estado` (`estado`),
  KEY `fk_notif_sucursal` (`id_sucursal`),
  CONSTRAINT `fk_notif_cita` FOREIGN KEY (`id_cita`) REFERENCES `cita` (`id_cita`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notif_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notif_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_notif_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursal` (`id_sucursal`),
  CONSTRAINT `fk_notif_tipo` FOREIGN KEY (`id_tipo_notificacion`) REFERENCES `tipo_notificacion` (`id_tipo_notificacion`) ON UPDATE CASCADE,
  CONSTRAINT `fk_notif_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_notif_canal` CHECK (`canal` in ('WHATSAPP','EMAIL','SMS','SISTEMA')),
  CONSTRAINT `chk_notif_estado` CHECK (`estado` in ('PENDIENTE','ENVIADA','FALLIDA','LEIDA'))
) ENGINE=InnoDB AUTO_INCREMENT=676 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificacion`
--

LOCK TABLES `notificacion` WRITE;
/*!40000 ALTER TABLE `notificacion` DISABLE KEYS */;
INSERT INTO `notificacion` VALUES (1,2,9,NULL,3,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:25','2026-08-08 13:05:35'),(2,2,7,NULL,4,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:05:25','2026-08-08 13:05:40'),(3,2,27,NULL,5,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:25','2026-08-08 13:05:44'),(4,2,8,NULL,6,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:05:25','2026-08-08 13:05:48'),(5,2,5,NULL,7,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 17:20.','ENVIADA','2026-08-08 13:05:25','2026-08-08 13:05:53'),(6,2,9,NULL,8,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:15.','ENVIADA','2026-08-08 13:05:25','2026-08-08 13:05:57'),(7,2,7,NULL,9,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:25.','ENVIADA','2026-08-08 13:05:25','2026-08-08 13:06:01'),(8,2,5,NULL,10,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 11:10.','ENVIADA','2026-08-08 13:05:26','2026-08-08 13:06:05'),(9,2,10,NULL,11,NULL,NULL,'EMAIL','Cita confirmada para el 10/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:26','2026-08-08 13:06:09'),(10,2,7,NULL,12,NULL,NULL,'EMAIL','Cita confirmada para el 10/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:05:26','2026-08-08 13:06:13'),(11,2,7,NULL,13,NULL,NULL,'EMAIL','Cita confirmada para el 10/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:26','2026-08-08 13:11:03'),(12,2,12,NULL,14,NULL,NULL,'EMAIL','Cita confirmada para el 11/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:26','2026-08-08 13:11:08'),(13,2,18,NULL,15,NULL,NULL,'EMAIL','Cita confirmada para el 11/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:26','2026-08-08 13:11:12'),(14,2,8,NULL,16,NULL,NULL,'EMAIL','Cita confirmada para el 12/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:26','2026-08-08 13:11:20'),(15,2,5,NULL,17,NULL,NULL,'EMAIL','Cita confirmada para el 12/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:26','2026-08-08 13:11:25'),(16,2,14,NULL,18,NULL,NULL,'EMAIL','Cita confirmada para el 12/08/2026 a las 15:25.','ENVIADA','2026-08-08 13:05:27','2026-08-08 13:11:29'),(17,2,8,NULL,19,NULL,NULL,'EMAIL','Cita confirmada para el 12/08/2026 a las 16:15.','ENVIADA','2026-08-08 13:05:27','2026-08-08 13:11:35'),(18,2,1,NULL,20,NULL,NULL,'EMAIL','Cita confirmada para el 13/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:05:27','2026-08-08 13:11:39'),(19,2,1,NULL,21,NULL,NULL,'EMAIL','Cita confirmada para el 13/08/2026 a las 09:45.','ENVIADA','2026-08-08 13:05:27','2026-08-08 13:11:43'),(20,2,11,NULL,22,NULL,NULL,'EMAIL','Cita confirmada para el 13/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:27','2026-08-08 13:11:49'),(21,2,10,NULL,23,NULL,NULL,'EMAIL','Cita confirmada para el 14/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:05:27','2026-08-08 13:16:58'),(22,2,1,NULL,24,NULL,NULL,'EMAIL','Cita confirmada para el 14/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:27','2026-08-08 13:17:03'),(23,2,33,NULL,25,NULL,NULL,'EMAIL','Cita confirmada para el 14/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:27','2026-08-08 13:17:07'),(24,2,14,NULL,26,NULL,NULL,'EMAIL','Cita confirmada para el 14/08/2026 a las 11:25.','ENVIADA','2026-08-08 13:05:28','2026-08-08 13:17:11'),(25,2,21,NULL,27,NULL,NULL,'EMAIL','Cita confirmada para el 14/08/2026 a las 13:50.','ENVIADA','2026-08-08 13:05:28','2026-08-08 13:17:16'),(26,2,30,NULL,28,NULL,NULL,'EMAIL','Cita confirmada para el 14/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:05:28','2026-08-08 13:17:27'),(27,2,22,NULL,29,NULL,NULL,'EMAIL','Cita confirmada para el 15/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:05:28','2026-08-08 13:17:31'),(28,2,8,NULL,30,NULL,NULL,'EMAIL','Cita confirmada para el 15/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:05:28','2026-08-08 13:17:36'),(29,2,9,NULL,31,NULL,NULL,'EMAIL','Cita confirmada para el 15/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:28','2026-08-08 13:17:40'),(30,2,10,NULL,32,NULL,NULL,'EMAIL','Cita confirmada para el 15/08/2026 a las 16:05.','ENVIADA','2026-08-08 13:05:28','2026-08-08 13:17:44'),(31,2,7,NULL,33,NULL,NULL,'EMAIL','Cita confirmada para el 15/08/2026 a las 10:05.','ENVIADA','2026-08-08 13:05:29','2026-08-08 13:22:28'),(32,2,34,NULL,34,NULL,NULL,'EMAIL','Cita confirmada para el 15/08/2026 a las 18:00.','ENVIADA','2026-08-08 13:05:29','2026-08-08 13:22:32'),(33,2,19,NULL,35,NULL,NULL,'EMAIL','Cita confirmada para el 15/08/2026 a las 08:45.','ENVIADA','2026-08-08 13:05:29','2026-08-08 13:22:37'),(34,2,1,NULL,36,NULL,NULL,'EMAIL','Cita confirmada para el 17/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:05:29','2026-08-08 13:22:41'),(35,2,12,NULL,37,NULL,NULL,'EMAIL','Cita confirmada para el 18/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:29','2026-08-08 13:22:45'),(36,2,7,NULL,38,NULL,NULL,'EMAIL','Cita confirmada para el 18/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:05:29','2026-08-08 13:22:49'),(37,2,9,NULL,39,NULL,NULL,'EMAIL','Cita confirmada para el 19/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:29','2026-08-08 13:22:54'),(38,2,34,NULL,40,NULL,NULL,'EMAIL','Cita confirmada para el 19/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:05:30','2026-08-08 13:22:58'),(39,2,7,NULL,41,NULL,NULL,'EMAIL','Cita confirmada para el 19/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:05:30','2026-08-08 13:23:02'),(40,2,16,NULL,42,NULL,NULL,'EMAIL','Cita confirmada para el 20/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:05:30','2026-08-08 13:23:06'),(41,2,31,NULL,43,NULL,NULL,'EMAIL','Cita confirmada para el 20/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:05:30','2026-08-08 13:28:30'),(42,2,5,NULL,44,NULL,NULL,'EMAIL','Cita confirmada para el 20/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:30','2026-08-08 13:28:35'),(43,2,12,NULL,45,NULL,NULL,'EMAIL','Cita confirmada para el 20/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:30','2026-08-08 13:28:39'),(44,2,32,NULL,46,NULL,NULL,'EMAIL','Cita confirmada para el 21/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:05:30','2026-08-08 13:28:43'),(45,1,9,NULL,3,NULL,NULL,'EMAIL','Te recordamos tu cita del 08/08/2026 a las 13:30 con Sofía Espínola.','ENVIADA','2026-08-08 13:05:31','2026-08-08 13:28:49'),(46,1,27,NULL,5,NULL,NULL,'EMAIL','Te recordamos tu cita del 08/08/2026 a las 13:30 con Marta Cáceres.','ENVIADA','2026-08-08 13:05:31','2026-08-08 13:28:53'),(47,1,5,NULL,7,NULL,NULL,'EMAIL','Te recordamos tu cita del 08/08/2026 a las 17:20 con Marta Cáceres.','ENVIADA','2026-08-08 13:05:31','2026-08-08 13:28:57'),(48,2,24,NULL,47,NULL,NULL,'EMAIL','Cita confirmada para el 21/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:06:13','2026-08-08 13:29:01'),(49,2,9,NULL,48,NULL,NULL,'EMAIL','Cita confirmada para el 21/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:06:14','2026-08-08 13:29:06'),(50,2,7,NULL,49,NULL,NULL,'EMAIL','Cita confirmada para el 21/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:14','2026-08-08 13:29:10'),(51,2,10,NULL,50,NULL,NULL,'EMAIL','Cita confirmada para el 21/08/2026 a las 08:55.','ENVIADA','2026-08-08 13:06:14','2026-08-08 13:33:32'),(52,2,5,NULL,51,NULL,NULL,'EMAIL','Cita confirmada para el 22/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:06:14','2026-08-08 13:33:36'),(53,2,26,NULL,52,NULL,NULL,'EMAIL','Cita confirmada para el 22/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:14','2026-08-08 13:33:40'),(54,2,7,NULL,53,NULL,NULL,'EMAIL','Cita confirmada para el 22/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:06:14','2026-08-08 13:33:44'),(55,2,8,NULL,54,NULL,NULL,'EMAIL','Cita confirmada para el 22/08/2026 a las 11:05.','ENVIADA','2026-08-08 13:06:14','2026-08-08 13:33:48'),(56,2,32,NULL,55,NULL,NULL,'EMAIL','Cita confirmada para el 22/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:15','2026-08-08 13:33:52'),(57,2,1,NULL,56,NULL,NULL,'EMAIL','Cita confirmada para el 24/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:15','2026-08-08 13:33:56'),(58,2,12,NULL,57,NULL,NULL,'EMAIL','Cita confirmada para el 25/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:06:15','2026-08-08 13:34:01'),(59,2,17,NULL,58,NULL,NULL,'EMAIL','Cita confirmada para el 25/08/2026 a las 08:35.','ENVIADA','2026-08-08 13:06:15','2026-08-08 13:34:05'),(60,2,25,NULL,59,NULL,NULL,'EMAIL','Cita confirmada para el 25/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:15','2026-08-08 13:34:09'),(61,2,8,NULL,60,NULL,NULL,'EMAIL','Cita confirmada para el 25/08/2026 a las 09:20.','ENVIADA','2026-08-08 13:06:15','2026-08-08 13:34:15'),(62,2,12,NULL,61,NULL,NULL,'EMAIL','Cita confirmada para el 26/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:06:15','2026-08-08 13:34:20'),(63,2,12,NULL,62,NULL,NULL,'EMAIL','Cita confirmada para el 26/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:16','2026-08-08 13:34:24'),(64,2,9,NULL,63,NULL,NULL,'EMAIL','Cita confirmada para el 26/08/2026 a las 10:05.','ENVIADA','2026-08-08 13:06:16','2026-08-08 13:34:28'),(65,2,23,NULL,64,NULL,NULL,'EMAIL','Cita confirmada para el 27/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:06:16','2026-08-08 13:34:32'),(66,2,9,NULL,65,NULL,NULL,'EMAIL','Cita confirmada para el 27/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:16','2026-08-08 13:34:36'),(67,2,8,NULL,66,NULL,NULL,'EMAIL','Cita confirmada para el 27/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:16','2026-08-08 13:34:40'),(68,2,5,NULL,67,NULL,NULL,'EMAIL','Cita confirmada para el 28/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:16','2026-08-08 13:34:45'),(69,2,5,NULL,68,NULL,NULL,'EMAIL','Cita confirmada para el 28/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:16','2026-08-08 13:34:49'),(70,2,10,NULL,69,NULL,NULL,'EMAIL','Cita confirmada para el 28/08/2026 a las 14:05.','ENVIADA','2026-08-08 13:06:17','2026-08-08 13:34:53'),(71,2,11,NULL,70,NULL,NULL,'EMAIL','Cita confirmada para el 28/08/2026 a las 15:30.','ENVIADA','2026-08-08 13:06:17','2026-08-08 13:34:57'),(72,2,7,NULL,71,NULL,NULL,'EMAIL','Cita confirmada para el 28/08/2026 a las 16:35.','ENVIADA','2026-08-08 13:06:17','2026-08-08 13:35:01'),(73,2,10,NULL,72,NULL,NULL,'EMAIL','Cita confirmada para el 29/08/2026 a las 08:00.','ENVIADA','2026-08-08 13:06:17','2026-08-08 13:35:05'),(74,2,9,NULL,73,NULL,NULL,'EMAIL','Cita confirmada para el 29/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:17','2026-08-08 13:35:09'),(75,2,8,NULL,74,NULL,NULL,'EMAIL','Cita confirmada para el 29/08/2026 a las 10:05.','ENVIADA','2026-08-08 13:06:17','2026-08-08 13:35:14'),(76,2,23,NULL,75,NULL,NULL,'EMAIL','Cita confirmada para el 29/08/2026 a las 11:10.','ENVIADA','2026-08-08 13:06:17','2026-08-08 13:35:19'),(77,2,28,NULL,76,NULL,NULL,'EMAIL','Cita confirmada para el 29/08/2026 a las 16:05.','ENVIADA','2026-08-08 13:06:18','2026-08-08 13:35:24'),(78,2,19,NULL,77,NULL,NULL,'EMAIL','Cita confirmada para el 31/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:18','2026-08-08 13:35:28'),(79,2,23,NULL,78,NULL,NULL,'EMAIL','Cita confirmada para el 31/08/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:18','2026-08-08 13:35:32'),(80,2,12,NULL,79,NULL,NULL,'EMAIL','Cita confirmada para el 31/08/2026 a las 14:50.','ENVIADA','2026-08-08 13:06:18','2026-08-08 13:35:36'),(81,2,31,NULL,80,NULL,NULL,'EMAIL','Cita confirmada para el 01/09/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:18','2026-08-08 13:35:41'),(82,2,8,NULL,81,NULL,NULL,'EMAIL','Cita confirmada para el 01/09/2026 a las 08:00.','ENVIADA','2026-08-08 13:06:19','2026-08-08 13:35:45'),(83,2,5,NULL,82,NULL,NULL,'EMAIL','Cita confirmada para el 01/09/2026 a las 08:00.','ENVIADA','2026-08-08 13:06:19','2026-08-08 13:35:50'),(84,2,9,NULL,83,NULL,NULL,'EMAIL','Cita confirmada para el 02/09/2026 a las 08:00.','ENVIADA','2026-08-08 13:06:19','2026-08-08 13:35:54'),(85,2,22,NULL,84,NULL,NULL,'EMAIL','Cita confirmada para el 02/09/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:19','2026-08-08 13:35:58'),(86,2,10,NULL,85,NULL,NULL,'EMAIL','Cita confirmada para el 03/09/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:19','2026-08-08 13:36:02'),(87,2,11,NULL,86,NULL,NULL,'EMAIL','Cita confirmada para el 03/09/2026 a las 14:35.','ENVIADA','2026-08-08 13:06:19','2026-08-08 13:36:06'),(88,2,30,NULL,87,NULL,NULL,'EMAIL','Cita confirmada para el 03/09/2026 a las 15:40.','ENVIADA','2026-08-08 13:06:19','2026-08-08 13:36:11'),(89,2,12,NULL,88,NULL,NULL,'EMAIL','Cita confirmada para el 04/09/2026 a las 08:00.','ENVIADA','2026-08-08 13:06:20','2026-08-08 13:36:15'),(90,2,31,NULL,89,NULL,NULL,'EMAIL','Cita confirmada para el 04/09/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:20','2026-08-08 13:36:20'),(91,2,12,NULL,90,NULL,NULL,'EMAIL','Cita confirmada para el 04/09/2026 a las 16:35.','ENVIADA','2026-08-08 13:06:20','2026-08-08 13:36:24'),(92,2,30,NULL,91,NULL,NULL,'EMAIL','Cita confirmada para el 04/09/2026 a las 17:55.','ENVIADA','2026-08-08 13:06:20','2026-08-08 13:36:29'),(93,2,12,NULL,92,NULL,NULL,'EMAIL','Cita confirmada para el 05/09/2026 a las 08:00.','ENVIADA','2026-08-08 13:06:21','2026-08-08 13:36:34'),(94,2,1,NULL,93,NULL,NULL,'EMAIL','Cita confirmada para el 05/09/2026 a las 13:30.','ENVIADA','2026-08-08 13:06:21','2026-08-08 13:36:38'),(95,2,25,NULL,94,NULL,NULL,'EMAIL','Cita confirmada para el 05/09/2026 a las 08:00.','ENVIADA','2026-08-08 13:06:21','2026-08-08 13:36:43'),(96,2,7,NULL,95,NULL,NULL,'EMAIL','Cita confirmada para el 05/09/2026 a las 10:35.','ENVIADA','2026-08-08 13:06:22','2026-08-08 13:36:47'),(97,2,10,NULL,96,NULL,NULL,'EMAIL','Cita confirmada para el 05/09/2026 a las 13:35.','ENVIADA','2026-08-08 13:06:22','2026-08-08 13:36:51'),(98,5,NULL,NULL,NULL,3,NULL,'SISTEMA','El producto Tintura quedo en 0.00 (minimo 0.00). Conviene reponer.','PENDIENTE','2026-08-08 13:08:58',NULL),(99,2,10,NULL,97,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:11:50','2026-08-08 13:36:56'),(100,2,12,NULL,98,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:11:50','2026-08-08 13:37:00'),(101,2,7,NULL,99,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:11:51','2026-08-08 13:37:04'),(102,2,8,NULL,100,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:11:52','2026-08-08 16:50:42'),(103,2,9,NULL,101,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:11:53','2026-08-08 16:50:47'),(104,3,9,NULL,101,NULL,NULL,'EMAIL','Tu cita fue cancelada.','ENVIADA','2026-08-08 13:11:53','2026-08-08 16:50:51'),(105,2,5,NULL,102,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:11:53','2026-08-08 16:50:56'),(106,2,1,NULL,103,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 11:00.','ENVIADA','2026-08-08 13:11:54','2026-08-08 16:51:02'),(107,2,29,NULL,104,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:11:54','2026-08-08 16:51:07'),(108,2,20,NULL,105,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:11:55','2026-08-08 16:51:12'),(109,5,NULL,NULL,NULL,11,NULL,'SISTEMA','El producto Shampoo  profesional 1L quedo en 0.00 (minimo 0.00). Conviene reponer.','PENDIENTE','2026-08-08 13:11:55',NULL),(110,2,8,NULL,106,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:11:56','2026-08-08 16:55:33'),(111,2,34,NULL,107,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:11:56','2026-08-08 16:51:29'),(112,5,NULL,NULL,NULL,4,NULL,'SISTEMA','El producto guantes de latex quedo en 0.00 (minimo 0.00). Conviene reponer.','PENDIENTE','2026-08-08 13:11:56',NULL),(113,2,7,NULL,108,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 11:00.','ENVIADA','2026-08-08 13:11:56','2026-08-08 16:51:34'),(114,2,7,NULL,109,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:11:57','2026-08-08 16:51:38'),(115,2,27,NULL,110,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:11:57','2026-08-08 16:51:44'),(116,2,9,NULL,111,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 16:30.','ENVIADA','2026-08-08 13:11:58','2026-08-08 16:51:58'),(117,2,12,NULL,112,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:11:59','2026-08-08 16:52:04'),(118,2,1,NULL,113,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:11:59','2026-08-08 16:52:09'),(119,2,7,NULL,114,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 16:30.','ENVIADA','2026-08-08 13:12:00','2026-08-08 16:52:14'),(120,2,11,NULL,115,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:01','2026-08-08 16:55:38'),(121,2,8,NULL,116,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:01','2026-08-08 16:52:39'),(122,2,11,NULL,117,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 16:30.','ENVIADA','2026-08-08 13:12:02','2026-08-08 16:52:45'),(123,2,9,NULL,118,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:03','2026-08-08 16:52:57'),(124,2,34,NULL,119,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:03','2026-08-08 16:53:02'),(125,3,34,NULL,119,NULL,NULL,'EMAIL','Tu cita fue cancelada.','ENVIADA','2026-08-08 13:12:03','2026-08-08 16:53:07'),(126,2,9,NULL,120,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 16:30.','ENVIADA','2026-08-08 13:12:03','2026-08-08 16:53:11'),(127,2,10,NULL,121,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:04','2026-08-08 16:53:16'),(128,2,20,NULL,122,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:05','2026-08-08 16:53:21'),(129,2,12,NULL,123,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:06','2026-08-08 16:53:26'),(130,2,12,NULL,124,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:06','2026-08-08 16:53:31'),(131,2,9,NULL,125,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:07','2026-08-08 16:53:36'),(132,2,12,NULL,126,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:08','2026-08-08 16:53:40'),(133,2,8,NULL,127,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 16:30.','ENVIADA','2026-08-08 13:12:08','2026-08-08 16:53:45'),(134,2,8,NULL,128,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:09','2026-08-08 16:53:51'),(135,5,NULL,NULL,NULL,9,NULL,'SISTEMA','El producto Guantes de latex (caja) quedo en 2.00 (minimo 2.00). Conviene reponer.','PENDIENTE','2026-08-08 13:12:09',NULL),(136,2,1,NULL,129,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:10','2026-08-08 16:53:55'),(137,2,33,NULL,130,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:11','2026-08-08 16:54:02'),(138,2,12,NULL,131,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:11','2026-08-08 16:54:07'),(139,2,10,NULL,132,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:12','2026-08-08 16:54:12'),(140,2,17,NULL,133,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:13','2026-08-08 16:54:17'),(141,2,34,NULL,134,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:13','2026-08-08 16:54:22'),(142,2,11,NULL,135,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:14','2026-08-08 16:54:27'),(143,2,17,NULL,136,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 16:30.','ENVIADA','2026-08-08 13:12:15','2026-08-08 16:54:32'),(144,2,1,NULL,137,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:16','2026-08-08 16:54:37'),(145,2,1,NULL,138,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:16','2026-08-08 16:54:42'),(146,2,12,NULL,139,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:17','2026-08-08 16:54:47'),(147,2,23,NULL,140,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:17','2026-08-08 16:54:52'),(148,2,8,NULL,141,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:18','2026-08-08 16:54:57'),(149,2,7,NULL,142,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:19','2026-08-08 16:55:02'),(150,3,7,NULL,142,NULL,NULL,'EMAIL','Tu cita fue cancelada.','ENVIADA','2026-08-08 13:12:19','2026-08-08 16:55:07'),(151,2,10,NULL,143,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 16:30.','ENVIADA','2026-08-08 13:12:19','2026-08-08 16:55:12'),(152,2,12,NULL,144,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:20','2026-08-08 16:55:18'),(153,2,9,NULL,145,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:20','2026-08-08 16:55:23'),(154,2,12,NULL,146,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:21','2026-08-08 16:55:28'),(155,2,15,NULL,147,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 16:30.','ENVIADA','2026-08-08 13:12:21','2026-08-08 16:55:43'),(156,2,5,NULL,148,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:22','2026-08-08 16:55:48'),(157,2,9,NULL,149,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:23','2026-08-08 16:55:53'),(158,2,13,NULL,150,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:23','2026-08-08 16:55:58'),(159,2,12,NULL,151,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:24','2026-08-08 16:56:02'),(160,2,22,NULL,152,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:25','2026-08-08 16:56:07'),(161,2,20,NULL,153,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 16:30.','ENVIADA','2026-08-08 13:12:25','2026-08-08 16:56:13'),(162,2,25,NULL,154,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:26','2026-08-08 16:56:17'),(163,2,9,NULL,155,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:27','2026-08-08 16:56:22'),(164,2,5,NULL,156,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 17:45.','ENVIADA','2026-08-08 13:12:27','2026-08-08 16:56:27'),(165,2,10,NULL,157,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:28','2026-08-08 16:56:32'),(166,2,1,NULL,158,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:28','2026-08-08 16:56:37'),(167,3,1,NULL,158,NULL,NULL,'EMAIL','Tu cita fue cancelada.','ENVIADA','2026-08-08 13:12:28','2026-08-08 16:56:41'),(168,2,10,NULL,159,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:29','2026-08-08 16:56:46'),(169,2,11,NULL,160,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:29','2026-08-08 16:56:51'),(170,2,1,NULL,161,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:30','2026-08-08 16:56:56'),(171,2,32,NULL,162,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:31','2026-08-08 16:57:01'),(172,2,10,NULL,163,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:31','2026-08-08 16:57:06'),(173,5,NULL,NULL,NULL,10,NULL,'SISTEMA','El producto Serum reparador 100ml quedo en 5.00 (minimo 5.00). Conviene reponer.','PENDIENTE','2026-08-08 13:12:31',NULL),(174,2,13,NULL,164,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:32','2026-08-08 16:57:10'),(175,2,12,NULL,165,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:33','2026-08-08 16:57:15'),(176,2,14,NULL,166,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:33','2026-08-08 16:57:26'),(177,3,14,NULL,166,NULL,NULL,'EMAIL','Tu cita fue cancelada.','ENVIADA','2026-08-08 13:12:33','2026-08-08 16:57:31'),(178,2,8,NULL,167,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 11:00.','ENVIADA','2026-08-08 13:12:33','2026-08-08 16:57:36'),(179,2,1,NULL,168,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 15:00.','ENVIADA','2026-08-08 13:12:34','2026-08-08 16:57:41'),(180,2,5,NULL,169,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:34','2026-08-08 16:57:47'),(181,2,8,NULL,170,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 09:30.','ENVIADA','2026-08-08 13:12:35','2026-08-08 16:57:52'),(182,2,1,NULL,171,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 11:00.','ENVIADA','2026-08-08 13:12:35','2026-08-08 16:57:56'),(183,3,12,NULL,37,NULL,NULL,'EMAIL','Marta Cáceres no va a estar disponible el 18/08/2026 a las 13:30 (Vacaciones). Podés reprogramar tu cita o elegir otro profesional.','ENVIADA','2026-08-08 13:15:02','2026-08-08 16:58:01'),(184,3,9,NULL,39,NULL,NULL,'EMAIL','Marta Cáceres no va a estar disponible el 19/08/2026 a las 13:30 (Vacaciones). Podés reprogramar tu cita o elegir otro profesional.','ENVIADA','2026-08-08 13:15:02','2026-08-08 16:58:06'),(185,3,12,NULL,37,NULL,NULL,'EMAIL','Marta Cáceres no va a estar disponible el 18/08/2026 a las 13:30 (Vacaciones). Podés reprogramar tu cita o elegir otro profesional.','ENVIADA','2026-08-08 13:17:46','2026-08-08 16:58:11'),(186,3,9,NULL,39,NULL,NULL,'EMAIL','Marta Cáceres no va a estar disponible el 19/08/2026 a las 13:30 (Vacaciones). Podés reprogramar tu cita o elegir otro profesional.','ENVIADA','2026-08-08 13:17:46','2026-08-08 16:58:16'),(187,2,1,NULL,172,NULL,NULL,'EMAIL','Cita confirmada para el 11/08/2026 a las 09:00.','ENVIADA','2026-08-08 13:25:01','2026-08-08 16:58:21'),(188,2,1,NULL,173,NULL,NULL,'EMAIL','Cita confirmada para el 08/08/2026 a las 08:30.','ENVIADA','2026-08-08 13:25:54','2026-08-08 16:58:25'),(189,2,11,NULL,174,NULL,NULL,'EMAIL','Cita confirmada para el 29/08/2026 a las 14:00.','ENVIADA','2026-08-08 16:59:58','2026-08-10 16:31:24'),(190,1,12,NULL,14,NULL,NULL,'EMAIL','Te recordamos tu cita del 11/08/2026 a las 13:30 con Sofía Espínola.','ENVIADA','2026-08-10 16:31:24','2026-08-10 16:31:24'),(191,1,1,NULL,172,NULL,NULL,'EMAIL','Te recordamos tu cita del 11/08/2026 a las 09:00 con Rocío Duarte.','ENVIADA','2026-08-10 16:31:24','2026-08-10 16:31:24'),(342,1,22,NULL,29,NULL,NULL,'EMAIL','Te recordamos tu cita del 15/08/2026 a las 08:00 con Rocío Duarte.','ENVIADA','2026-08-14 15:49:23','2026-08-14 15:49:28'),(343,1,8,NULL,30,NULL,NULL,'EMAIL','Te recordamos tu cita del 15/08/2026 a las 08:00 con Lucía Benítez.','PENDIENTE','2026-08-14 15:49:23',NULL),(344,1,9,NULL,31,NULL,NULL,'EMAIL','Te recordamos tu cita del 15/08/2026 a las 13:30 con Marta Cáceres.','PENDIENTE','2026-08-14 15:49:23',NULL),(345,1,7,NULL,33,NULL,NULL,'EMAIL','Te recordamos tu cita del 15/08/2026 a las 10:05 con Lucía Benítez.','PENDIENTE','2026-08-14 15:49:23',NULL),(346,1,19,NULL,35,NULL,NULL,'EMAIL','Te recordamos tu cita del 15/08/2026 a las 08:45 con Rocío Duarte.','PENDIENTE','2026-08-14 15:49:23',NULL),(604,5,NULL,NULL,NULL,10,1,'SISTEMA','El producto Serum reparador 100ml quedo en 0.0000 (minimo 5.00) en Peluqueria (local unico). Conviene reponer.','PENDIENTE','2026-08-17 08:50:25',NULL);
/*!40000 ALTER TABLE `notificacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `pago_personal`
--

DROP TABLE IF EXISTS `pago_personal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pago_personal` (
  `id_pago_personal` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_usuario_registro` int(10) unsigned NOT NULL,
  `id_metodo_pago` int(10) unsigned DEFAULT NULL,
  `id_estado_pago` int(10) unsigned NOT NULL,
  `id_caja` int(10) unsigned DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `periodo` varchar(40) DEFAULT NULL,
  `observaciones` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`id_pago_personal`),
  KEY `idx_pp_usuario` (`id_usuario`),
  KEY `idx_pp_usuario_reg` (`id_usuario_registro`),
  KEY `idx_pp_estado` (`id_estado_pago`),
  KEY `fk_pagopers_metodo` (`id_metodo_pago`),
  KEY `fk_pagopers_caja` (`id_caja`),
  CONSTRAINT `fk_pagopers_caja` FOREIGN KEY (`id_caja`) REFERENCES `caja` (`id_caja`),
  CONSTRAINT `fk_pagopers_metodo` FOREIGN KEY (`id_metodo_pago`) REFERENCES `metodo_pago` (`id_metodo_pago`),
  CONSTRAINT `fk_pp_estado` FOREIGN KEY (`id_estado_pago`) REFERENCES `estado_pago_personal` (`id_estado_pago`) ON UPDATE CASCADE,
  CONSTRAINT `fk_pp_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE,
  CONSTRAINT `fk_pp_usuario_registro` FOREIGN KEY (`id_usuario_registro`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pago_personal`
--

LOCK TABLES `pago_personal` WRITE;
/*!40000 ALTER TABLE `pago_personal` DISABLE KEYS */;
INSERT INTO `pago_personal` VALUES (2,8,1,NULL,4,NULL,'2026-08-08 13:17:47','2026-07',NULL);
/*!40000 ALTER TABLE `pago_personal` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_pagopersonal_au
AFTER UPDATE ON pago_personal FOR EACH ROW
BEGIN
  IF OLD.id_estado_pago NOT IN (3, 4) AND NEW.id_estado_pago IN (3, 4) THEN
    INSERT INTO auditoria (id_usuario, accion, modulo, tabla_afectada, id_registro, detalle)
    VALUES (COALESCE(@usuario_actual, NEW.id_usuario_registro),
            IF(NEW.id_estado_pago = 4, 'REVERTIR', 'ANULAR'), 'Pagos', 'pago_personal', NEW.id_pago_personal,
            CONCAT('Pago al personal del periodo ', COALESCE(NEW.periodo, 'sin periodo')));
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `pago_proveedor`
--

DROP TABLE IF EXISTS `pago_proveedor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pago_proveedor` (
  `id_pago_proveedor` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_proveedor` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_metodo_pago` int(10) unsigned NOT NULL,
  `id_estado_pago_proveedor` int(10) unsigned NOT NULL,
  `id_caja` int(10) unsigned DEFAULT NULL,
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `referencia` varchar(100) DEFAULT NULL,
  `observaciones` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`id_pago_proveedor`),
  KEY `idx_pprov_proveedor` (`id_proveedor`),
  KEY `idx_pprov_usuario` (`id_usuario`),
  KEY `idx_pprov_metodo` (`id_metodo_pago`),
  KEY `idx_pprov_estado` (`id_estado_pago_proveedor`),
  KEY `idx_pprov_caja` (`id_caja`),
  CONSTRAINT `fk_pprov_caja` FOREIGN KEY (`id_caja`) REFERENCES `caja` (`id_caja`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_pprov_estado` FOREIGN KEY (`id_estado_pago_proveedor`) REFERENCES `estado_pago_proveedor` (`id_estado_pago_proveedor`) ON UPDATE CASCADE,
  CONSTRAINT `fk_pprov_metodo` FOREIGN KEY (`id_metodo_pago`) REFERENCES `metodo_pago` (`id_metodo_pago`) ON UPDATE CASCADE,
  CONSTRAINT `fk_pprov_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedor` (`id_proveedor`) ON UPDATE CASCADE,
  CONSTRAINT `fk_pprov_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pago_proveedor`
--

LOCK TABLES `pago_proveedor` WRITE;
/*!40000 ALTER TABLE `pago_proveedor` DISABLE KEYS */;
INSERT INTO `pago_proveedor` VALUES (2,3,1,1,2,2,'2026-08-04 22:22:14',NULL,NULL),(3,4,1,4,2,4,'2026-08-08 13:20:26','Transferencia parcial',NULL);
/*!40000 ALTER TABLE `pago_proveedor` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_pagoproveedor_au
AFTER UPDATE ON pago_proveedor FOR EACH ROW
BEGIN
  IF OLD.id_estado_pago_proveedor <> 2 AND NEW.id_estado_pago_proveedor = 2 THEN
    INSERT INTO auditoria (id_usuario, accion, modulo, tabla_afectada, id_registro, detalle)
    VALUES (COALESCE(@usuario_actual, NEW.id_usuario), 'ANULAR', 'Proveedores', 'pago_proveedor', NEW.id_pago_proveedor,
            CONCAT('Pago a proveedor anulado. Monto: ', fn_pago_proveedor_monto(NEW.id_pago_proveedor)));
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `persona`
--

DROP TABLE IF EXISTS `persona`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `persona` (
  `id_persona` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `apellido` varchar(80) DEFAULT NULL,
  `cedula` varchar(20) DEFAULT NULL,
  `ruc` varchar(20) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `fecha_alta` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_persona`),
  UNIQUE KEY `uq_persona_cedula` (`cedula`),
  UNIQUE KEY `uq_persona_ruc` (`ruc`),
  KEY `idx_persona_nombre` (`apellido`,`nombre`),
  KEY `idx_persona_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `persona`
--

LOCK TABLES `persona` WRITE;
/*!40000 ALTER TABLE `persona` DISABLE KEYS */;
INSERT INTO `persona` VALUES (1,'Ana','Propietaria',NULL,NULL,NULL,'admin@peluqueria.com',NULL,NULL,'2026-08-06 15:48:43'),(2,'Ana','Gimenez',NULL,NULL,'0981-000000','ana.cliente@example.com',NULL,NULL,'2026-08-06 15:48:43'),(3,'Pablo','Gonzalez',NULL,NULL,'0984323230','cacerespablo13m@gmail.com',NULL,NULL,'2026-08-06 15:48:43'),(4,'14K Company',NULL,NULL,'12345678-1','0987267342','admiwadn@gmail.com','efwer',NULL,'2026-08-06 15:48:43'),(5,'Lucía','Benítez','3111001',NULL,'0981111001','lucia@peluqueria.test',NULL,NULL,'2026-08-08 13:00:33'),(6,'Marta','Cáceres','3111002',NULL,'0981111002','marta@peluqueria.test',NULL,NULL,'2026-08-08 13:00:33'),(7,'Rocío','Duarte','3111003',NULL,'0981111003','rocio@peluqueria.test',NULL,NULL,'2026-08-08 13:00:33'),(8,'Sofía','Espínola','3111004',NULL,'0981111004','sofia@peluqueria.test',NULL,NULL,'2026-08-08 13:00:34'),(9,'Carmen','Fretes','3111005',NULL,'0981111005','carmen@peluqueria.test',NULL,NULL,'2026-08-08 13:00:34'),(10,'Gloria','Garay','3111006',NULL,'0981111006','gloria@peluqueria.test',NULL,NULL,'2026-08-08 13:00:35'),(11,'Distribuidora Capilar SA',NULL,NULL,'80012345-6','021555000','ventas@6c1b5e.com.py','Asunción',NULL,'2026-08-08 13:00:38'),(12,'Belleza Import SRL',NULL,NULL,'80098765-1','021555000','ventas@7faa08.com.py','Asunción',NULL,'2026-08-08 13:00:38'),(13,'Andrea','Villalba','4200000',NULL,'0981100000','avillalba0@correo.test',NULL,'1990-01-10','2026-08-08 13:00:38'),(14,'Beatriz','Rojas','4200001',NULL,'0981100001','brojas1@correo.test',NULL,'1991-02-11','2026-08-08 13:00:38'),(15,'Carla','Mendoza','4200002',NULL,'0981100002','cmendoza2@correo.test',NULL,'1992-03-12','2026-08-08 13:00:38'),(16,'Diana','Ayala','4200003',NULL,'0981100003','dayala3@correo.test',NULL,'1993-04-13','2026-08-08 13:00:38'),(17,'Elena','Sanabria','4200004',NULL,'0981100004','esanabria4@correo.test',NULL,'1994-05-14','2026-08-08 13:00:39'),(18,'Fátima','Ocampos','4200005',NULL,'0981100005','focampos5@correo.test',NULL,'1995-06-15','2026-08-08 13:00:39'),(19,'Gabriela','Riveros','4200006',NULL,'0981100006','griveros6@correo.test',NULL,'1996-07-16','2026-08-08 13:00:39'),(20,'Hilda','Cabrera','4200007',NULL,'0981100007','hcabrera7@correo.test',NULL,'1997-08-17','2026-08-08 13:00:39'),(21,'Julia','Bogado','4200009',NULL,'0981100009','jbogado9@correo.test',NULL,'1999-01-19','2026-08-08 13:00:39'),(22,'Mónica','Paredes','4200012',NULL,'0981100012','mparedes12@correo.test',NULL,'1992-04-12','2026-08-08 13:00:39'),(23,'Norma','Aquino','4200013',NULL,'0981100013','naquino13@correo.test',NULL,'1993-05-13','2026-08-08 13:00:40'),(24,'Patricia','Torres','4200015',NULL,'0981100015','ptorres15@correo.test',NULL,'1995-07-15','2026-08-08 13:00:40'),(25,'Rosa','Vera','4200016',NULL,'0981100016','rvera16@correo.test',NULL,'1996-08-16','2026-08-08 13:00:40'),(26,'Silvia','Acosta','4200017',NULL,'0981100017','sacosta17@correo.test',NULL,'1997-09-17','2026-08-08 13:00:40'),(27,'Teresa','Franco','4200018',NULL,'0981100018','tfranco18@correo.test',NULL,'1998-01-18','2026-08-08 13:00:40'),(28,'Ximena','Ledesma','4200021',NULL,'0981100021','xledesma21@correo.test',NULL,'1991-04-11','2026-08-08 13:00:40'),(29,'Yolanda','Barrios','4200022',NULL,'0981100022','ybarrios22@correo.test',NULL,'1992-05-12','2026-08-08 13:00:40'),(30,'Zulma','Escobar','4200023',NULL,'0981100023','zescobar23@correo.test',NULL,'1993-06-13','2026-08-08 13:00:41'),(31,'Javier','Cardozo','4200024',NULL,'0981100024','jcardozo24@correo.test',NULL,'1994-07-14','2026-08-08 13:00:41'),(32,'Marcos','Segovia','4200025',NULL,'0981100025','msegovia25@correo.test',NULL,'1995-08-15','2026-08-08 13:00:41'),(33,'Rubén','Maidana','4200027',NULL,'0981100027','rmaidana27@correo.test',NULL,'1997-01-17','2026-08-08 13:00:41'),(34,'Irene','Zárate','4200008',NULL,'0981100008','izarate8@correo.test',NULL,'1998-09-18','2026-08-08 13:01:59'),(35,'Karina','Núñez','4200010',NULL,'0981100010','knunez10@correo.test',NULL,'1990-02-10','2026-08-08 13:01:59'),(36,'Laura','Insfrán','4200011',NULL,'0981100011','linsfran11@correo.test',NULL,'1991-03-11','2026-08-08 13:01:59'),(37,'Olga','Ramírez','4200014',NULL,'0981100014','oramirez14@correo.test',NULL,'1994-06-14','2026-08-08 13:01:59'),(38,'Verónica','Giménez','4200019',NULL,'0981100019','vgimenez19@correo.test',NULL,'1999-02-19','2026-08-08 13:01:59'),(39,'Wilma','Martínez','4200020',NULL,'0981100020','wmartinez20@correo.test',NULL,'1990-03-10','2026-08-08 13:01:59'),(40,'Óscar','Britez','4200026',NULL,'0981100026','obritez26@correo.test',NULL,'1996-09-16','2026-08-08 13:01:59'),(41,'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA','Test','8888881',NULL,NULL,'largo@t.test',NULL,NULL,'2026-08-08 13:23:12'),(42,'Test','Test','\'; DROP TABLE client',NULL,NULL,'sqli@t.test',NULL,NULL,'2026-08-08 13:23:12'),(43,'<script>alert(1)</script>','XSS','8888883',NULL,NULL,'xss@t.test',NULL,NULL,'2026-08-08 13:23:12');
/*!40000 ALTER TABLE `persona` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `preferencia_cliente`
--

DROP TABLE IF EXISTS `preferencia_cliente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `preferencia_cliente` (
  `id_preferencia` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cliente` int(10) unsigned NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_preferencia`),
  KEY `idx_pref_cliente` (`id_cliente`),
  CONSTRAINT `fk_pref_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `preferencia_cliente`
--

LOCK TABLES `preferencia_cliente` WRITE;
/*!40000 ALTER TABLE `preferencia_cliente` DISABLE KEYS */;
/*!40000 ALTER TABLE `preferencia_cliente` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `preferencia_recordatorio`
--

DROP TABLE IF EXISTS `preferencia_recordatorio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `preferencia_recordatorio` (
  `id_cliente` int(10) unsigned NOT NULL,
  `dias_antes` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_cliente`),
  CONSTRAINT `fk_prefrec_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_prefrec_dias` CHECK (`dias_antes` between 0 and 15)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `preferencia_recordatorio`
--

LOCK TABLES `preferencia_recordatorio` WRITE;
/*!40000 ALTER TABLE `preferencia_recordatorio` DISABLE KEYS */;
INSERT INTO `preferencia_recordatorio` VALUES (1,2,1);
/*!40000 ALTER TABLE `preferencia_recordatorio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `preferencia_usuario`
--

DROP TABLE IF EXISTS `preferencia_usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `preferencia_usuario` (
  `id_usuario` int(10) unsigned NOT NULL,
  `tema` varchar(10) NOT NULL DEFAULT 'claro' COMMENT 'Tema de la interfaz: claro u oscuro',
  `biometrico_activo` tinyint(1) NOT NULL DEFAULT 0,
  `biometrico_pregunt` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_usuario`),
  CONSTRAINT `fk_prefusr_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_pref_tema` CHECK (`tema` in ('claro','oscuro'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `preferencia_usuario`
--

LOCK TABLES `preferencia_usuario` WRITE;
/*!40000 ALTER TABLE `preferencia_usuario` DISABLE KEYS */;
INSERT INTO `preferencia_usuario` VALUES (1,'claro',0,1),(2,'claro',0,1);
/*!40000 ALTER TABLE `preferencia_usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `producto`
--

DROP TABLE IF EXISTS `producto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `producto` (
  `id_producto` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_categoria` int(10) unsigned NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `unidad_medida` varchar(20) NOT NULL DEFAULT 'unidad',
  `precio_costo` decimal(12,2) NOT NULL DEFAULT 0.00,
  `precio_venta` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tasa_iva` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `contenido` decimal(10,2) DEFAULT NULL,
  `unidad_consumo` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id_producto`),
  KEY `idx_producto_categoria` (`id_categoria`),
  KEY `idx_producto_nombre` (`nombre`),
  CONSTRAINT `fk_producto_categoria` FOREIGN KEY (`id_categoria`) REFERENCES `categoria_producto` (`id_categoria`) ON UPDATE CASCADE,
  CONSTRAINT `chk_producto_costo` CHECK (`precio_costo` >= 0),
  CONSTRAINT `chk_producto_venta` CHECK (`precio_venta` >= 0),
  CONSTRAINT `chk_producto_iva` CHECK (`tasa_iva` in (0,5,10)),
  CONSTRAINT `chk_producto_contenido` CHECK (`contenido` is null or `contenido` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `producto`
--

LOCK TABLES `producto` WRITE;
/*!40000 ALTER TABLE `producto` DISABLE KEYS */;
INSERT INTO `producto` VALUES (3,2,'Tintura',NULL,'unidad',50000.00,50000.00,10,1,NULL,NULL),(4,4,'guantes de latex',NULL,'unidad',10000.00,10000.00,10,1,NULL,NULL),(5,2,'Shampoo profesional 1L','Shampoo profesional 1L','unidad',85000.00,140000.00,10,1,1000.00,'ml'),(6,2,'Acondicionador 1L','Acondicionador 1L','unidad',90000.00,150000.00,10,1,1000.00,'ml'),(7,1,'Tintura profesional','Tintura profesional','unidad',45000.00,75000.00,10,1,NULL,NULL),(8,1,'Agua oxigenada 900ml','Agua oxigenada 900ml','unidad',30000.00,50000.00,10,1,900.00,'ml'),(9,3,'Guantes de latex (caja)','Guantes de latex (caja)','caja',25000.00,40000.00,10,1,100.00,'par'),(10,5,'Serum reparador 100ml','Serum reparador 100ml','unidad',60000.00,110000.00,10,1,NULL,NULL),(11,2,'Shampoo  profesional 1L',NULL,'unidad',85000.00,85000.00,10,1,NULL,NULL);
/*!40000 ALTER TABLE `producto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `producto_sucursal`
--

DROP TABLE IF EXISTS `producto_sucursal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `producto_sucursal` (
  `id_producto` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  `stock_minimo` decimal(10,2) NOT NULL DEFAULT 0.00,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_producto`,`id_sucursal`),
  KEY `fk_prodsuc_sucursal` (`id_sucursal`),
  CONSTRAINT `fk_prodsuc_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`),
  CONSTRAINT `fk_prodsuc_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursal` (`id_sucursal`),
  CONSTRAINT `chk_prodsuc_minimo` CHECK (`stock_minimo` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `producto_sucursal`
--

LOCK TABLES `producto_sucursal` WRITE;
/*!40000 ALTER TABLE `producto_sucursal` DISABLE KEYS */;
INSERT INTO `producto_sucursal` VALUES (3,1,0.00,1),(4,1,0.00,1),(5,1,3.00,1),(6,1,3.00,1),(7,1,10.00,1),(8,1,4.00,1),(9,1,2.00,1),(10,1,5.00,1),(11,1,0.00,1);
/*!40000 ALTER TABLE `producto_sucursal` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `producto_utilizado`
--

DROP TABLE IF EXISTS `producto_utilizado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `producto_utilizado` (
  `id_producto_utilizado` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_servicio_realizado` int(10) unsigned NOT NULL,
  `id_producto` int(10) unsigned NOT NULL,
  `cantidad` decimal(12,4) NOT NULL DEFAULT 0.0000,
  PRIMARY KEY (`id_producto_utilizado`),
  UNIQUE KEY `uq_prod_util` (`id_servicio_realizado`,`id_producto`),
  KEY `idx_pu_producto` (`id_producto`),
  CONSTRAINT `fk_pu_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`) ON UPDATE CASCADE,
  CONSTRAINT `fk_pu_servicio_realizado` FOREIGN KEY (`id_servicio_realizado`) REFERENCES `servicio_realizado` (`id_servicio_realizado`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_pu_cantidad` CHECK (`cantidad` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `producto_utilizado`
--

LOCK TABLES `producto_utilizado` WRITE;
/*!40000 ALTER TABLE `producto_utilizado` DISABLE KEYS */;
INSERT INTO `producto_utilizado` VALUES (2,2,8,0.0400),(3,4,8,0.0300),(4,4,11,1.0000),(5,6,6,0.0500),(6,6,3,1.0000),(7,8,5,0.0400),(8,9,8,0.0400),(9,11,6,0.0300),(10,13,6,0.0400),(11,16,5,0.0500),(12,17,5,0.0500),(13,18,8,0.0200),(14,19,6,0.0500),(15,19,4,1.0000),(16,21,5,0.0200),(17,21,7,1.0000),(18,22,6,0.0300),(19,24,8,0.0200),(20,25,5,0.0500),(21,25,11,1.0000),(24,28,5,0.0200),(25,28,4,1.0000),(26,29,6,0.0300),(29,32,6,0.0200),(30,33,6,0.0400),(31,33,9,1.0000),(32,34,6,0.0300),(33,34,10,1.0000),(34,36,8,0.0300),(35,37,8,0.0600),(36,37,10,1.0000),(41,42,6,0.0200),(42,43,5,0.0200),(43,43,10,1.0000),(44,45,5,0.0500),(45,45,10,1.0000),(46,47,6,0.0400),(47,49,5,0.0500),(48,49,9,1.0000),(49,51,5,0.0500),(50,52,8,0.0200),(51,54,6,0.0300),(52,54,9,1.0000),(53,56,6,0.0300),(54,57,5,0.0500),(55,57,9,1.0000),(56,58,5,0.0400),(57,60,5,0.0300),(58,61,5,0.0400),(59,63,8,0.0600),(60,63,7,1.0000),(63,65,5,0.0400),(64,65,10,1.0000),(65,66,6,0.0400),(66,66,7,1.0000),(67,67,5,0.0200),(68,68,5,0.0500),(71,72,6,0.0200),(72,74,6,0.0300),(73,75,6,0.0400),(74,76,6,0.0400),(77,79,5,0.0300),(78,79,9,1.0000),(79,81,6,0.0200),(80,81,9,1.0000),(81,83,8,0.0200),(82,84,8,0.0300),(85,86,5,0.0300),(86,88,5,0.0400),(87,88,7,1.0000),(88,89,8,0.0400),(89,90,8,0.0300),(90,90,10,1.0000),(93,92,8,0.0400),(98,96,6,0.0400),(99,97,6,0.0200),(100,97,7,1.0000),(103,100,8,0.0300),(104,100,10,1.0000),(105,101,6,0.0400),(106,101,7,1.0000),(107,102,6,0.0400),(112,107,5,0.0300),(115,110,8,0.0400);
/*!40000 ALTER TABLE `producto_utilizado` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_produtil_ai AFTER INSERT ON producto_utilizado
FOR EACH ROW
BEGIN
  DECLARE v_usuario  INT UNSIGNED;
  DECLARE v_sucursal INT UNSIGNED;

  SELECT sr.id_usuario, c.id_sucursal INTO v_usuario, v_sucursal
  FROM servicio_realizado sr
  JOIN cita c ON c.id_cita = sr.id_cita
  WHERE sr.id_servicio_realizado = NEW.id_servicio_realizado;

  INSERT INTO movimiento_inventario (id_producto, id_sucursal, id_usuario, id_tipo_movimiento,
                                     cantidad, referencia, observaciones)
  VALUES (NEW.id_producto, v_sucursal, v_usuario, 2, NEW.cantidad,
          CONCAT('SR#', NEW.id_servicio_realizado), 'Consumo durante el servicio');
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `proveedor`
--

DROP TABLE IF EXISTS `proveedor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedor` (
  `id_proveedor` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `contacto` varchar(120) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `id_persona` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_proveedor`),
  KEY `fk_prov_persona` (`id_persona`),
  CONSTRAINT `fk_prov_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedor`
--

LOCK TABLES `proveedor` WRITE;
/*!40000 ALTER TABLE `proveedor` DISABLE KEYS */;
INSERT INTO `proveedor` VALUES (3,'Rafael',1,4),(4,'Rodrigo Vera',1,11),(5,'Nadia Ortiz',1,12);
/*!40000 ALTER TABLE `proveedor` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rol`
--

DROP TABLE IF EXISTS `rol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rol` (
  `id_rol` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(150) DEFAULT NULL,
  `es_personal` tinyint(1) NOT NULL DEFAULT 1,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_rol`),
  UNIQUE KEY `uq_rol_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rol`
--

LOCK TABLES `rol` WRITE;
/*!40000 ALTER TABLE `rol` DISABLE KEYS */;
INSERT INTO `rol` VALUES (1,'Administrador','Acceso total al sistema, cuentas y configuración',1,1),(2,'Profesional','Empleado que atiende las citas del salón',1,1),(3,'Asistente administrativo','Operación diaria: citas, clientes, caja e inventario',1,1),(4,'Cliente','Acceso al portal del cliente',0,1),(6,'Gerente','Supervisa la operación diaria',1,1);
/*!40000 ALTER TABLE `rol` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rol_modulo`
--

DROP TABLE IF EXISTS `rol_modulo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rol_modulo` (
  `id_rol` int(10) unsigned NOT NULL,
  `modulo` varchar(40) NOT NULL,
  PRIMARY KEY (`id_rol`,`modulo`),
  CONSTRAINT `fk_rolmod_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rol_modulo`
--

LOCK TABLES `rol_modulo` WRITE;
/*!40000 ALTER TABLE `rol_modulo` DISABLE KEYS */;
INSERT INTO `rol_modulo` VALUES (2,'citas.agenda'),(2,'citas.atencion'),(2,'clientes.fidelizacion'),(2,'clientes.registro'),(2,'clientes.valoraciones'),(2,'facturacion.cobros'),(2,'facturacion.facturas'),(2,'seguridad.asistencia'),(3,'citas.agenda'),(3,'citas.atencion'),(3,'clientes.canjes'),(3,'clientes.fidelizacion'),(3,'clientes.registro'),(3,'clientes.valoraciones'),(3,'facturacion.caja'),(3,'facturacion.cobros'),(3,'facturacion.facturas'),(3,'facturacion.pagos'),(3,'facturacion.proveedores'),(3,'inventario.compras'),(3,'inventario.productos'),(3,'inventario.proveedores'),(3,'inventario.stock'),(3,'reportes'),(3,'seguridad.asistencia'),(3,'seguridad.turnos'),(3,'servicios.catalogo'),(3,'servicios.categorias'),(3,'servicios.descuentos'),(6,'citas.agenda'),(6,'citas.atencion'),(6,'clientes.fidelizacion'),(6,'clientes.registro'),(6,'facturacion.caja'),(6,'facturacion.cobros'),(6,'facturacion.facturas'),(6,'inventario.compras'),(6,'inventario.productos'),(6,'inventario.stock'),(6,'reportes'),(6,'seguridad.asistencia'),(6,'seguridad.comisiones'),(6,'seguridad.turnos'),(6,'servicios.catalogo');
/*!40000 ALTER TABLE `rol_modulo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sena_solicitud`
--

DROP TABLE IF EXISTS `sena_solicitud`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sena_solicitud` (
  `id_solicitud` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cita` int(10) unsigned NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `id_cobro` int(10) unsigned DEFAULT NULL,
  `id_usuario` int(10) unsigned DEFAULT NULL COMMENT 'quien confirmo o rechazo',
  `rechazada_en` datetime DEFAULT NULL,
  `motivo` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id_solicitud`),
  KEY `ix_senasol_cita` (`id_cita`),
  KEY `ix_senasol_cobro` (`id_cobro`),
  KEY `ix_senasol_usuario` (`id_usuario`),
  CONSTRAINT `fk_senasol_cita` FOREIGN KEY (`id_cita`) REFERENCES `cita` (`id_cita`),
  CONSTRAINT `fk_senasol_cobro` FOREIGN KEY (`id_cobro`) REFERENCES `cobro` (`id_cobro`),
  CONSTRAINT `fk_senasol_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`),
  CONSTRAINT `chk_senasol_monto` CHECK (`monto` > 0),
  CONSTRAINT `chk_senasol_estado` CHECK (`id_cobro` is null or `rechazada_en` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sena_solicitud`
--

LOCK TABLES `sena_solicitud` WRITE;
/*!40000 ALTER TABLE `sena_solicitud` DISABLE KEYS */;
/*!40000 ALTER TABLE `sena_solicitud` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servicio`
--

DROP TABLE IF EXISTS `servicio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `servicio` (
  `id_servicio` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_categoria_servicio` int(10) unsigned NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `precio` decimal(12,2) NOT NULL DEFAULT 0.00,
  `duracion_min` int(11) NOT NULL DEFAULT 0,
  `tasa_iva` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `requiere_exclusividad` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_servicio`),
  UNIQUE KEY `uq_servicio_nombre` (`nombre`),
  KEY `idx_servicio_categoria` (`id_categoria_servicio`),
  CONSTRAINT `fk_servicio_categoria` FOREIGN KEY (`id_categoria_servicio`) REFERENCES `categoria_servicio` (`id_categoria_servicio`) ON UPDATE CASCADE,
  CONSTRAINT `chk_servicio_precio` CHECK (`precio` >= 0),
  CONSTRAINT `chk_servicio_duracion` CHECK (`duracion_min` >= 0),
  CONSTRAINT `chk_servicio_iva` CHECK (`tasa_iva` in (0,5,10))
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicio`
--

LOCK TABLES `servicio` WRITE;
/*!40000 ALTER TABLE `servicio` DISABLE KEYS */;
INSERT INTO `servicio` VALUES (3,1,'Corte de dama',NULL,75000.00,45,10,1,0),(4,1,'Corte de caballero','Corte de caballero',50000.00,30,10,1,1),(5,1,'Corte de niño','Corte de niño',40000.00,30,10,1,1),(6,2,'Coloración completa','Coloración completa',280000.00,120,10,1,1),(7,2,'Mechas / balayage','Mechas / balayage',350000.00,150,10,1,1),(8,3,'Tratamiento capilar','Tratamiento capilar',150000.00,60,10,1,1),(9,3,'Keratina','Keratina',420000.00,180,10,1,1),(10,4,'Brushing','Brushing',60000.00,40,10,1,1),(11,4,'Peinado de fiesta','Peinado de fiesta',180000.00,75,10,1,1),(12,5,'Manicura','Manicura',55000.00,40,10,1,0),(13,5,'Pedicura','Pedicura',65000.00,50,10,1,0),(14,6,'Lavado y acondicionado','Lavado y acondicionado',25000.00,15,10,1,0);
/*!40000 ALTER TABLE `servicio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servicio_canjeable`
--

DROP TABLE IF EXISTS `servicio_canjeable`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `servicio_canjeable` (
  `id_servicio_canjeable` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_servicio` int(10) unsigned NOT NULL,
  `puntos` int(11) NOT NULL,
  `dias_vigencia` int(11) NOT NULL DEFAULT 30,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_servicio_canjeable`),
  UNIQUE KEY `uq_servcanje_servicio` (`id_servicio`),
  CONSTRAINT `fk_servcanje_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicio` (`id_servicio`),
  CONSTRAINT `chk_servcanje_puntos` CHECK (`puntos` > 0),
  CONSTRAINT `chk_servcanje_vigencia` CHECK (`dias_vigencia` between 1 and 365)
) ENGINE=InnoDB AUTO_INCREMENT=135 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicio_canjeable`
--

LOCK TABLES `servicio_canjeable` WRITE;
/*!40000 ALTER TABLE `servicio_canjeable` DISABLE KEYS */;
INSERT INTO `servicio_canjeable` VALUES (1,6,3000,30,1),(2,14,2000,30,1);
/*!40000 ALTER TABLE `servicio_canjeable` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servicio_descuento`
--

DROP TABLE IF EXISTS `servicio_descuento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `servicio_descuento` (
  `id_servicio_descuento` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_servicio` int(10) unsigned NOT NULL,
  `id_descuento` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_servicio_descuento`),
  UNIQUE KEY `uq_servicio_descuento` (`id_servicio`,`id_descuento`),
  KEY `idx_sd_descuento` (`id_descuento`),
  CONSTRAINT `fk_sd_descuento` FOREIGN KEY (`id_descuento`) REFERENCES `descuento` (`id_descuento`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sd_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicio` (`id_servicio`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicio_descuento`
--

LOCK TABLES `servicio_descuento` WRITE;
/*!40000 ALTER TABLE `servicio_descuento` DISABLE KEYS */;
/*!40000 ALTER TABLE `servicio_descuento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `servicio_realizado`
--

DROP TABLE IF EXISTS `servicio_realizado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `servicio_realizado` (
  `id_servicio_realizado` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cita` int(10) unsigned NOT NULL,
  `id_servicio` int(10) unsigned NOT NULL,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_detalle_factura` int(10) unsigned DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `observaciones` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`id_servicio_realizado`),
  KEY `idx_sr_cita` (`id_cita`),
  KEY `idx_sr_servicio` (`id_servicio`),
  KEY `idx_sr_usuario` (`id_usuario`),
  KEY `idx_sr_detalle` (`id_detalle_factura`),
  CONSTRAINT `fk_sr_cita` FOREIGN KEY (`id_cita`) REFERENCES `cita` (`id_cita`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sr_detalle_factura` FOREIGN KEY (`id_detalle_factura`) REFERENCES `detalle_factura` (`id_detalle_factura`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_sr_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicio` (`id_servicio`) ON UPDATE CASCADE,
  CONSTRAINT `fk_sr_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=198 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicio_realizado`
--

LOCK TABLES `servicio_realizado` WRITE;
/*!40000 ALTER TABLE `servicio_realizado` DISABLE KEYS */;
INSERT INTO `servicio_realizado` VALUES (2,4,10,10,2,'2026-08-08 13:08:57','Atención registrada'),(3,4,12,10,3,'2026-08-08 13:08:57','Atención registrada'),(4,6,5,8,5,'2026-08-08 13:08:58','Atención registrada'),(5,6,10,8,6,'2026-08-08 13:08:58','Atención registrada'),(6,8,4,8,8,'2026-08-08 13:08:58','Atención registrada'),(7,8,13,8,9,'2026-08-08 13:08:58','Atención registrada'),(8,9,5,10,11,'2026-08-08 13:08:59','Atención registrada'),(9,10,13,8,12,'2026-08-08 13:09:00','Atención registrada'),(10,10,14,8,13,'2026-08-08 13:09:00','Atención registrada'),(11,3,5,11,15,'2026-08-08 13:09:01','Atención registrada'),(12,3,11,11,16,'2026-08-08 13:09:01','Atención registrada'),(13,5,3,9,18,'2026-08-08 13:09:01','Atención registrada'),(14,5,5,9,19,'2026-08-08 13:09:01','Atención registrada'),(15,5,7,9,20,'2026-08-08 13:09:01','Atención registrada'),(16,7,8,9,21,'2026-08-08 13:09:02','Atención registrada'),(17,97,4,8,22,'2026-07-10 09:30:00',NULL),(18,98,4,10,23,'2026-07-10 09:30:00',NULL),(19,99,4,11,24,'2026-07-10 15:00:00',NULL),(20,99,6,11,25,'2026-07-10 15:00:00',NULL),(21,100,4,9,27,'2026-07-10 15:00:00',NULL),(22,102,4,8,28,'2026-07-11 09:30:00',NULL),(23,102,13,8,29,'2026-07-11 09:30:00',NULL),(24,103,3,10,31,'2026-07-11 11:00:00',NULL),(25,105,5,8,32,'2026-07-13 09:30:00',NULL),(28,107,3,10,33,'2026-07-14 09:30:00',NULL),(29,108,10,10,34,'2026-07-14 11:00:00',NULL),(30,108,13,10,35,'2026-07-14 11:00:00',NULL),(32,111,3,9,37,'2026-07-15 16:30:00',NULL),(33,112,7,9,38,'2026-07-16 15:00:00',NULL),(34,113,8,11,39,'2026-07-16 15:00:00',NULL),(35,113,12,11,40,'2026-07-16 15:00:00',NULL),(36,114,14,11,42,'2026-07-16 16:30:00',NULL),(37,115,4,10,43,'2026-07-17 09:30:00',NULL),(38,115,12,10,44,'2026-07-17 09:30:00',NULL),(42,118,7,11,46,'2026-07-18 15:00:00',NULL),(43,120,3,11,47,'2026-07-18 16:30:00',NULL),(44,120,10,11,48,'2026-07-18 16:30:00',NULL),(45,121,4,8,50,'2026-07-20 09:30:00',NULL),(46,121,10,8,51,'2026-07-20 09:30:00',NULL),(47,122,3,10,53,'2026-07-20 09:30:00',NULL),(48,122,10,10,54,'2026-07-20 09:30:00',NULL),(49,123,10,10,56,'2026-07-21 09:30:00',NULL),(50,123,13,10,57,'2026-07-21 09:30:00',NULL),(51,124,9,9,59,'2026-07-21 15:00:00',NULL),(52,125,9,11,60,'2026-07-22 15:00:00',NULL),(53,125,12,11,61,'2026-07-22 15:00:00',NULL),(54,126,5,10,63,'2026-07-22 09:30:00',NULL),(55,126,10,10,64,'2026-07-22 09:30:00',NULL),(56,127,6,11,66,'2026-07-22 16:30:00',NULL),(57,128,9,10,67,'2026-07-23 09:30:00',NULL),(58,129,13,8,68,'2026-07-23 09:30:00',NULL),(59,129,14,8,69,'2026-07-23 09:30:00',NULL),(60,130,6,9,71,'2026-07-23 15:00:00',NULL),(61,131,5,8,72,'2026-07-24 09:30:00',NULL),(62,131,12,8,73,'2026-07-24 09:30:00',NULL),(63,132,14,10,75,'2026-07-24 09:30:00',NULL),(65,134,6,9,76,'2026-07-25 15:00:00',NULL),(66,135,12,10,77,'2026-07-25 09:30:00',NULL),(67,136,12,11,78,'2026-07-25 16:30:00',NULL),(68,137,4,9,79,'2026-07-27 15:00:00',NULL),(69,137,9,9,80,'2026-07-27 15:00:00',NULL),(72,140,5,9,82,'2026-07-28 15:00:00',NULL),(73,140,7,9,83,'2026-07-28 15:00:00',NULL),(74,141,14,11,85,'2026-07-28 15:00:00',NULL),(75,143,5,9,86,'2026-07-29 16:30:00',NULL),(76,144,6,10,87,'2026-07-29 09:30:00',NULL),(77,144,12,10,88,'2026-07-29 09:30:00',NULL),(79,146,8,11,90,'2026-07-30 15:00:00',NULL),(80,146,9,11,91,'2026-07-30 15:00:00',NULL),(81,147,3,9,93,'2026-07-30 16:30:00',NULL),(82,147,14,9,94,'2026-07-30 16:30:00',NULL),(83,148,5,10,96,'2026-07-30 09:30:00',NULL),(84,149,12,8,97,'2026-07-31 09:30:00',NULL),(86,151,4,11,98,'2026-07-31 15:00:00',NULL),(87,151,14,11,99,'2026-07-31 15:00:00',NULL),(88,152,10,9,101,'2026-08-01 15:00:00',NULL),(89,153,7,9,102,'2026-08-01 16:30:00',NULL),(90,154,10,10,103,'2026-08-01 09:30:00',NULL),(92,156,10,9,104,'2026-08-01 17:45:00',NULL),(96,160,6,9,105,'2026-08-04 15:00:00',NULL),(97,161,5,10,106,'2026-08-04 09:30:00',NULL),(100,163,11,8,107,'2026-08-05 09:30:00',NULL),(101,164,5,8,108,'2026-08-06 09:30:00',NULL),(102,165,3,11,109,'2026-08-06 15:00:00',NULL),(103,165,5,11,110,'2026-08-06 15:00:00',NULL),(107,169,4,8,112,'2026-08-07 09:30:00',NULL),(110,171,11,10,113,'2026-08-07 11:00:00',NULL),(111,173,4,10,NULL,'2026-08-08 13:25:54',NULL);
/*!40000 ALTER TABLE `servicio_realizado` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER trg_srealizado_bi
BEFORE INSERT ON servicio_realizado FOR EACH ROW
BEGIN
  IF fn_es_personal(NEW.id_usuario) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El servicio debe registrarse a nombre de un usuario del personal.';
  END IF;

  IF fn_puede_realizar(NEW.id_usuario, NEW.id_servicio) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ese profesional no esta habilitado para ese servicio.';
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `servicio_sucursal`
--

DROP TABLE IF EXISTS `servicio_sucursal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `servicio_sucursal` (
  `id_servicio` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_servicio`,`id_sucursal`),
  KEY `ix_servsuc_sucursal` (`id_sucursal`),
  CONSTRAINT `fk_servsuc_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicio` (`id_servicio`),
  CONSTRAINT `fk_servsuc_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursal` (`id_sucursal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicio_sucursal`
--

LOCK TABLES `servicio_sucursal` WRITE;
/*!40000 ALTER TABLE `servicio_sucursal` DISABLE KEYS */;
INSERT INTO `servicio_sucursal` VALUES (3,1),(4,1),(5,1),(6,1),(7,1),(8,1),(9,1),(10,1),(11,1),(12,1),(13,1),(14,1);
/*!40000 ALTER TABLE `servicio_sucursal` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sucursal`
--

DROP TABLE IF EXISTS `sucursal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sucursal` (
  `id_sucursal` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `ruc` varchar(20) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `direccion` varchar(200) DEFAULT NULL,
  `ciudad` varchar(60) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_sucursal`),
  UNIQUE KEY `uq_sucursal_ruc` (`ruc`)
) ENGINE=InnoDB AUTO_INCREMENT=184 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sucursal`
--

LOCK TABLES `sucursal` WRITE;
/*!40000 ALTER TABLE `sucursal` DISABLE KEYS */;
INSERT INTO `sucursal` VALUES (1,'Peluqueria (local unico)','80000000-0',NULL,NULL,'Luque',1);
/*!40000 ALTER TABLE `sucursal` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `timbrado`
--

DROP TABLE IF EXISTS `timbrado`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `timbrado` (
  `id_timbrado` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_sucursal` int(10) unsigned NOT NULL,
  `id_tipo_comprobante` int(10) unsigned NOT NULL,
  `nro_timbrado` varchar(20) NOT NULL,
  `establecimiento` char(3) NOT NULL DEFAULT '001',
  `punto_expedicion` char(3) NOT NULL DEFAULT '001',
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date NOT NULL,
  `nro_desde` int(10) unsigned NOT NULL DEFAULT 1,
  `nro_hasta` int(10) unsigned NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_timbrado`),
  UNIQUE KEY `uq_timbrado` (`nro_timbrado`,`id_tipo_comprobante`,`establecimiento`,`punto_expedicion`),
  KEY `idx_timbrado_sucursal` (`id_sucursal`),
  KEY `idx_timbrado_tipo` (`id_tipo_comprobante`),
  CONSTRAINT `fk_timbrado_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursal` (`id_sucursal`) ON UPDATE CASCADE,
  CONSTRAINT `fk_timbrado_tipo` FOREIGN KEY (`id_tipo_comprobante`) REFERENCES `tipo_comprobante` (`id_tipo_comprobante`) ON UPDATE CASCADE,
  CONSTRAINT `chk_timbrado_fechas` CHECK (`fecha_fin` >= `fecha_inicio`),
  CONSTRAINT `chk_timbrado_rango` CHECK (`nro_hasta` >= `nro_desde`),
  CONSTRAINT `chk_timbrado_nro` CHECK (`nro_timbrado` regexp '^[0-9]{8}$'),
  CONSTRAINT `chk_timbrado_est` CHECK (`establecimiento` regexp '^[0-9]{3}$'),
  CONSTRAINT `chk_timbrado_pun` CHECK (`punto_expedicion` regexp '^[0-9]{3}$'),
  CONSTRAINT `chk_timbrado_rango7` CHECK (`nro_desde` >= 1 and `nro_hasta` <= 9999999 and `nro_desde` <= `nro_hasta`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timbrado`
--

LOCK TABLES `timbrado` WRITE;
/*!40000 ALTER TABLE `timbrado` DISABLE KEYS */;
INSERT INTO `timbrado` VALUES (1,1,1,'12345678','001','001','2026-01-01','2026-12-31',1,9999999,1),(2,1,5,'12345679','001','001','2026-01-01','2026-12-31',1,9999999,1);
/*!40000 ALTER TABLE `timbrado` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipo_ausencia`
--

DROP TABLE IF EXISTS `tipo_ausencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipo_ausencia` (
  `id_tipo_ausencia` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(40) NOT NULL,
  PRIMARY KEY (`id_tipo_ausencia`),
  UNIQUE KEY `uq_tipo_ausencia_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipo_ausencia`
--

LOCK TABLES `tipo_ausencia` WRITE;
/*!40000 ALTER TABLE `tipo_ausencia` DISABLE KEYS */;
INSERT INTO `tipo_ausencia` VALUES (4,'Bloqueo puntual'),(3,'Feriado'),(2,'Licencia'),(1,'Vacaciones');
/*!40000 ALTER TABLE `tipo_ausencia` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipo_comprobante`
--

DROP TABLE IF EXISTS `tipo_comprobante`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipo_comprobante` (
  `id_tipo_comprobante` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(5) NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `signo` tinyint(4) NOT NULL DEFAULT 1,
  `requiere_origen` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_tipo_comprobante`),
  UNIQUE KEY `uq_tipo_comprobante_codigo` (`codigo`),
  UNIQUE KEY `uq_tipo_comprobante_nombre` (`nombre`),
  CONSTRAINT `chk_tcomp_signo` CHECK (`signo` in (-1,0,1))
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipo_comprobante`
--

LOCK TABLES `tipo_comprobante` WRITE;
/*!40000 ALTER TABLE `tipo_comprobante` DISABLE KEYS */;
INSERT INTO `tipo_comprobante` VALUES (1,'01','Factura',1,0,1),(2,'02','Boleta de venta',1,0,0),(3,'03','Ticket',1,0,0),(4,'04','Autofactura',1,0,0),(5,'05','Nota de credito',-1,1,1),(6,'06','Nota de debito',1,1,0),(7,'07','Nota de remision',0,0,0),(8,'08','Comprobante de pago',1,0,1);
/*!40000 ALTER TABLE `tipo_comprobante` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipo_movimiento_inventario`
--

DROP TABLE IF EXISTS `tipo_movimiento_inventario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipo_movimiento_inventario` (
  `id_tipo_movimiento` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `signo` char(1) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_tipo_movimiento`),
  UNIQUE KEY `uq_tipo_mov_nombre` (`nombre`),
  CONSTRAINT `chk_tipo_mov_signo` CHECK (`signo` in ('E','S'))
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipo_movimiento_inventario`
--

LOCK TABLES `tipo_movimiento_inventario` WRITE;
/*!40000 ALTER TABLE `tipo_movimiento_inventario` DISABLE KEYS */;
INSERT INTO `tipo_movimiento_inventario` VALUES (1,'Compra','E',1),(2,'Consumo en servicio','S',1),(3,'Ajuste positivo','E',1),(4,'Ajuste negativo','S',1),(5,'Merma o vencimiento','S',1),(6,'Devolucion de cliente','E',1),(7,'Venta de producto','S',1),(8,'Devolucion a proveedor','S',1),(9,'Inventario inicial','E',1);
/*!40000 ALTER TABLE `tipo_movimiento_inventario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipo_notificacion`
--

DROP TABLE IF EXISTS `tipo_notificacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipo_notificacion` (
  `id_tipo_notificacion` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `destinatario` varchar(10) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_tipo_notificacion`),
  UNIQUE KEY `uq_tipo_notif_nombre` (`nombre`),
  CONSTRAINT `chk_tipo_notif_dest` CHECK (`destinatario` in ('CLIENTE','INTERNO'))
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipo_notificacion`
--

LOCK TABLES `tipo_notificacion` WRITE;
/*!40000 ALTER TABLE `tipo_notificacion` DISABLE KEYS */;
INSERT INTO `tipo_notificacion` VALUES (1,'Recordatorio de cita','CLIENTE',1),(2,'Confirmacion de cita','CLIENTE',1),(3,'Cancelacion de cita','CLIENTE',1),(4,'Promocion','CLIENTE',1),(5,'Alerta de stock minimo','INTERNO',1),(6,'Cierre de caja','INTERNO',1);
/*!40000 ALTER TABLE `tipo_notificacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `token_cita`
--

DROP TABLE IF EXISTS `token_cita`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `token_cita` (
  `id_token` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_cita` int(10) unsigned NOT NULL,
  `codigo` char(48) NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_token`),
  UNIQUE KEY `uq_tokencita_codigo` (`codigo`),
  KEY `idx_tokencita_cita` (`id_cita`),
  CONSTRAINT `fk_tokencita_cita` FOREIGN KEY (`id_cita`) REFERENCES `cita` (`id_cita`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=173 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `token_cita`
--

LOCK TABLES `token_cita` WRITE;
/*!40000 ALTER TABLE `token_cita` DISABLE KEYS */;
INSERT INTO `token_cita` VALUES (1,3,'b99db4a91d0257c2c3e03adc289a3fb7e6f2f23827d686db','2026-09-07 13:05:31',0,'2026-08-08 13:05:31'),(2,4,'edbb55e074200e8ee5766fd3310918e870f031481f4a9b01','2026-09-07 13:05:35',0,'2026-08-08 13:05:35'),(3,5,'283ec81cfcae483be43ac240ea636363246bc2db0aecad6e','2026-09-07 13:05:40',0,'2026-08-08 13:05:40'),(4,6,'5d7e57bde83dfd07f4912aa600dfe433ee585f8bff1c29b1','2026-09-07 13:05:44',0,'2026-08-08 13:05:44'),(5,7,'a00a7a515bd99e5a7040df155c03b240b6b1e26b57974614','2026-09-07 13:05:48',0,'2026-08-08 13:05:48'),(6,8,'460fb3c7b20748475df236c6b366171aa82d6c180f05e65b','2026-09-07 13:05:53',0,'2026-08-08 13:05:53'),(7,9,'414151bd71873781c0b31d599714ccf92b4f93ab4c60cc24','2026-09-07 13:05:57',0,'2026-08-08 13:05:57'),(8,10,'b0443debfe8409733262aed42919fe0b1c8c4d9f458fd306','2026-09-07 13:06:01',0,'2026-08-08 13:06:01'),(9,11,'ee798bad19af3ed7877a690c1e547d36ef7a42efffc71c1b','2026-09-07 13:06:05',0,'2026-08-08 13:06:05'),(10,12,'d756e4f4fb0aa8e22d98d3cfd19497d899f6497ac0b28a07','2026-09-07 13:06:09',0,'2026-08-08 13:06:09'),(11,13,'ff58ad3d71e5b0cff6c402aaa330db9fb8e121f8935750bc','2026-09-07 13:10:59',0,'2026-08-08 13:10:59'),(12,14,'62cb529b8930de8a017c260ed8f640048e95ac520e8aec28','2026-09-07 13:11:03',0,'2026-08-08 13:11:03'),(13,15,'7cadf0c8d35f66cd089814cc17e29f8dcf9f5edf13f79d3a','2026-09-07 13:11:08',0,'2026-08-08 13:11:08'),(14,16,'ba216cf30389bc914d957c3381601345cca84235305c54b2','2026-09-07 13:11:12',0,'2026-08-08 13:11:12'),(15,17,'e8ad323d3ca68fcfdb11d0a9ddeb491df638a9bf807c8207','2026-09-07 13:11:20',0,'2026-08-08 13:11:20'),(16,18,'6f5c917c0ce3cb336410fca065f5dbb748463e7cf824024e','2026-09-07 13:11:25',0,'2026-08-08 13:11:25'),(17,19,'36dd37a2ee041861790c2bd7dc93c07a712ef680729d52ad','2026-09-07 13:11:29',0,'2026-08-08 13:11:29'),(18,20,'e1544d9471b000e7512f79e4ff5819ca18b03a8ac330ed63','2026-09-07 13:11:35',0,'2026-08-08 13:11:35'),(19,21,'e039d44758371d7a60304edb8ddf4b3466aca7b166bac5ab','2026-09-07 13:11:39',0,'2026-08-08 13:11:39'),(20,22,'0c0eda2e989ee4b17eae3dba2531d952996b2170a02978d1','2026-09-07 13:11:43',0,'2026-08-08 13:11:43'),(21,23,'2d83a9f66c89ce98185e4f65926d9bd3cbe4eee464fd78f7','2026-09-07 13:16:54',0,'2026-08-08 13:16:54'),(22,24,'fd9d556ce3840318b3fc74ceb27890806130062294b5e1bc','2026-09-07 13:16:58',0,'2026-08-08 13:16:58'),(23,25,'93e07c7f1f7e27b4d7b8e91ce849b9404c5ff5eea62268c5','2026-09-07 13:17:03',0,'2026-08-08 13:17:03'),(24,26,'d71a00381f708ba9cec15494b144c09dfbc75122e982381d','2026-09-07 13:17:07',0,'2026-08-08 13:17:07'),(25,27,'fffc420fc40e3064e51dba275b90885d47a0d513b0b7adf6','2026-09-07 13:17:11',0,'2026-08-08 13:17:11'),(26,28,'dcb127dd2fd9a195003710d04f334e0e9d2078e342ac173c','2026-09-07 13:17:16',0,'2026-08-08 13:17:16'),(27,29,'a118fbffb8d28367d0724a7576bc7fc943f29a915779884d','2026-09-07 13:17:27',0,'2026-08-08 13:17:27'),(28,30,'47b7dd592b4f856a08c13035ce694856581c623f8c8170f1','2026-09-07 13:17:31',0,'2026-08-08 13:17:31'),(29,31,'b783c1fb2e82c44d44af540d0fd6353df575c628d6e6db54','2026-09-07 13:17:36',0,'2026-08-08 13:17:36'),(30,32,'8da418c169ebb306949b0e1f0889ab497a55f6085d8ab32b','2026-09-07 13:17:40',0,'2026-08-08 13:17:40'),(31,33,'356dc9cdf40e5778ba5490daa07c5272d02fbb8538fd35c2','2026-09-07 13:22:24',0,'2026-08-08 13:22:24'),(32,34,'0bc6c7cf318a9b53d88b9dedceb806a6c89f24ea9f949040','2026-09-07 13:22:28',0,'2026-08-08 13:22:28'),(33,35,'bf168c63c054d676d06ddd486a86f583b2e7849fa6e5864e','2026-09-07 13:22:33',0,'2026-08-08 13:22:33'),(34,36,'a4f7463391a5b97ed569469d5f18d7df12dacbb238802861','2026-09-07 13:22:37',0,'2026-08-08 13:22:37'),(35,37,'cf5705671258c8e6332a16be4592858ea6dac2d3f1c94004','2026-09-07 13:22:41',0,'2026-08-08 13:22:41'),(36,38,'208de3ee88504655922123a7c51765677e92c8700bbe87fa','2026-09-07 13:22:45',0,'2026-08-08 13:22:45'),(37,39,'b916ae75b8fae63c894860084a490fcb2c8c10322407db0c','2026-09-07 13:22:49',0,'2026-08-08 13:22:49'),(38,40,'a7283670f65c8a656ad1ce7fde6c145930b09a15788d68f6','2026-09-07 13:22:54',0,'2026-08-08 13:22:54'),(39,41,'a750a6a02c597c64d95d521ef9886e71997b85e03f6dd1de','2026-09-07 13:22:58',0,'2026-08-08 13:22:58'),(40,42,'fa9cbb8922252a04306255f0fc4e108f930fa455a7a1ff24','2026-09-07 13:23:02',0,'2026-08-08 13:23:02'),(41,43,'7eb68dd2153cbf0e00c614d8216e0e8f73a82c3e235755f4','2026-09-07 13:28:26',0,'2026-08-08 13:28:26'),(42,44,'6daf4d65faa3eb2d5fbe1d7edb08a5028252963635031ebc','2026-09-07 13:28:30',0,'2026-08-08 13:28:30'),(43,45,'ee0ace1d482f2d1176e874e1eabc67c257cd96fd051e8c80','2026-09-07 13:28:35',0,'2026-08-08 13:28:35'),(44,46,'ff89afa48b55f86472f165ebeff1b0e6df9b00ac8c0c974e','2026-09-07 13:28:39',0,'2026-08-08 13:28:39'),(45,47,'c99b3386346ae752552146c160ddc2b7c456e916d398b128','2026-09-07 13:28:57',0,'2026-08-08 13:28:57'),(46,48,'79d5492d6fd728ff9857029d02892f680b7656a47cf8ccde','2026-09-07 13:29:02',0,'2026-08-08 13:29:02'),(47,49,'c0a2a3969a15fddba624746c6517afaf4321036dd870d0d7','2026-09-07 13:29:06',0,'2026-08-08 13:29:06'),(48,50,'b723576362d4b8c79488e33fb477b80e819085c7a11fc809','2026-09-07 13:33:27',0,'2026-08-08 13:33:27'),(49,51,'8396262bcc3ec4bac255651bcd968139142cf3a4b91a0980','2026-09-07 13:33:32',0,'2026-08-08 13:33:32'),(50,52,'ffb5290df0da3dfc786c919b788a695d6cad627e9f951061','2026-09-07 13:33:36',0,'2026-08-08 13:33:36'),(51,53,'3c23bb37d66eeaa56d43e91409e90186e570cc74fcaa01fb','2026-09-07 13:33:40',0,'2026-08-08 13:33:40'),(52,54,'15315809d56522e59f0fafd574634501b9335ff9a25f3639','2026-09-07 13:33:44',0,'2026-08-08 13:33:44'),(53,55,'24468966dbf75b086d4707fcc27c9f9f26f5c71deeab0fc6','2026-09-07 13:33:48',0,'2026-08-08 13:33:48'),(54,56,'7c4490933979bba3ac0f836479d8f67681c67937d51e6c54','2026-09-07 13:33:52',0,'2026-08-08 13:33:52'),(55,57,'812dbc6b0232274c567eec4bf2865ffe579e39849fdcb125','2026-09-07 13:33:56',0,'2026-08-08 13:33:56'),(56,58,'1bd4135bddc3c0ac5a9eed91dc2e98e7a8ffd87ecdcf8afb','2026-09-07 13:34:01',0,'2026-08-08 13:34:01'),(57,59,'58d6b524b64a93b2c3edef9d4bc41045a32279b42592e692','2026-09-07 13:34:05',0,'2026-08-08 13:34:05'),(58,60,'5401c0d01bf722107c49a47e84ded76fcf00b32d813f3779','2026-09-07 13:34:09',0,'2026-08-08 13:34:09'),(59,61,'2fa53c38ef73093379c95948b56bff342203cf0ce516c452','2026-09-07 13:34:15',0,'2026-08-08 13:34:15'),(60,62,'ccd574de95d2bc1360213e2065ba52e973279b2ef365ff5e','2026-09-07 13:34:20',0,'2026-08-08 13:34:20'),(61,63,'884eaee8d970c14e0386489fe37714ae5a60c56c0b94f598','2026-09-07 13:34:24',0,'2026-08-08 13:34:24'),(62,64,'03db295091d9dc2909ce43c6ee4c7e3adccaba1632244d42','2026-09-07 13:34:28',0,'2026-08-08 13:34:28'),(63,65,'cd9b628302a0719e410d2f6479df761981fb9891624742f1','2026-09-07 13:34:32',0,'2026-08-08 13:34:32'),(64,66,'cdb3b7d1f81fe197c86f413b89e829e87e36baf882d017ce','2026-09-07 13:34:36',0,'2026-08-08 13:34:36'),(65,67,'eb9b85901063f558f52e51ddccc2eef2c9bbc1710295072d','2026-09-07 13:34:40',0,'2026-08-08 13:34:40'),(66,68,'cbaacf3eb41e2b33931e05e1789d326e203d5cd33aad19e6','2026-09-07 13:34:45',0,'2026-08-08 13:34:45'),(67,69,'9167d420059a3f45bb28ffb7a9472c04aadabd4fe9f58f74','2026-09-07 13:34:49',0,'2026-08-08 13:34:49'),(68,70,'f212b36f6e497d8918b4772ec77cfbf32a724d76fbfa9598','2026-09-07 13:34:53',0,'2026-08-08 13:34:53'),(69,71,'10739cb131c6ffc3d5b37ade2182eef904a24aa4c3b5a36f','2026-09-07 13:34:57',0,'2026-08-08 13:34:57'),(70,72,'1b6cb5579627ae12fdc8870792c00722bad7dd8494aa7b9b','2026-09-07 13:35:01',0,'2026-08-08 13:35:01'),(71,73,'159a4344146662712928abe09b346094d4eb79de2997a783','2026-09-07 13:35:05',0,'2026-08-08 13:35:05'),(72,74,'02f11baf1f86eb412f4848faa3e40fdef0884f9659f9d7d3','2026-09-07 13:35:09',0,'2026-08-08 13:35:09'),(73,75,'09c8578674ad39cbf2fe7f9858312b1d476e2dd3adc71591','2026-09-07 13:35:14',0,'2026-08-08 13:35:14'),(74,76,'6fb999869c055d27272f0d4efa87b801378bdc121705e0ea','2026-09-07 13:35:19',0,'2026-08-08 13:35:19'),(75,77,'a61f5536eae85190867bf2cf391bb86b789ea33f0e0d25ed','2026-09-07 13:35:24',0,'2026-08-08 13:35:24'),(76,78,'c2fb5870c110743ecb14ef2dc29da99ddf04cc79d8857c35','2026-09-07 13:35:28',0,'2026-08-08 13:35:28'),(77,79,'6b614e266fef0263942561d58d4d25ab38c081c21bfc0cf1','2026-09-07 13:35:32',0,'2026-08-08 13:35:32'),(78,80,'88462497db00fb7d372309279021b912a35afc0b73e1dca8','2026-09-07 13:35:36',0,'2026-08-08 13:35:36'),(79,81,'67c2b53dc3ce99f86a9c13e42233fb47982d6d70e590f893','2026-09-07 13:35:41',0,'2026-08-08 13:35:41'),(80,82,'ccc1741f44a3cea2cc75ba93478f74152742fc6d600a6eb3','2026-09-07 13:35:45',0,'2026-08-08 13:35:45'),(81,83,'d88c13c54bbb39c34ab4f0b8ac51e937c4ada46a3619d193','2026-09-07 13:35:50',0,'2026-08-08 13:35:50'),(82,84,'2efc34a88cbbb241b4fa723a2c889ded4e6bec0a7ec6ea7b','2026-09-07 13:35:54',0,'2026-08-08 13:35:54'),(83,85,'cda716bade88523fee3e1f8b977d77bd41ce6e0debf9c033','2026-09-07 13:35:58',0,'2026-08-08 13:35:58'),(84,86,'8f62d0c7ec7389414823b49f55bde70ad5817e8217b541d8','2026-09-07 13:36:02',0,'2026-08-08 13:36:02'),(85,87,'bbf2939e75ed1c8db920215bf07fd7910ccf94a6d9ca97bd','2026-09-07 13:36:06',0,'2026-08-08 13:36:06'),(86,88,'b599d3358582afba4eeb2385be2e42d77215a9d8e5eca470','2026-09-07 13:36:11',0,'2026-08-08 13:36:11'),(87,89,'7e2c83349000a791b7adcf2133ef1ba5cfc99fd0bee4bf08','2026-09-07 13:36:15',0,'2026-08-08 13:36:15'),(88,90,'e2c163cef03eb9239c896b2727de19a315e614a8dae2b502','2026-09-07 13:36:20',0,'2026-08-08 13:36:20'),(89,91,'dedf6b39714cf14f5a785d89dfd25b2105e1f0d8d6915c28','2026-09-07 13:36:24',0,'2026-08-08 13:36:24'),(90,92,'aa4b1db4b8035f90f657f488a550df3a6fd8a18391b8ef5f','2026-09-07 13:36:29',0,'2026-08-08 13:36:29'),(91,93,'da3a3e3e1673441f02558c0050f725594edb87279a9e36bf','2026-09-07 13:36:34',0,'2026-08-08 13:36:34'),(92,94,'fbeecabc22be2c5d69c0010e6b7ea5189b65d3550a0f8233','2026-09-07 13:36:38',0,'2026-08-08 13:36:38'),(93,95,'000b7bc307c0f65619b5f3a9809dde748c946f6b30b2f25f','2026-09-07 13:36:43',0,'2026-08-08 13:36:43'),(94,96,'b004738f8725c538d7883b100dbb28af873a7e3c427e8f34','2026-09-07 13:36:47',0,'2026-08-08 13:36:47'),(95,97,'d2d7a3bb17777d7e8db9459328a1fcd9008b97edcb66d8f6','2026-09-07 13:36:51',0,'2026-08-08 13:36:51'),(96,98,'37fd6713b041e65f59b48ce5c6de525cadce5925f0d44b20','2026-09-07 13:36:56',0,'2026-08-08 13:36:56'),(97,99,'b731f24b8d25ed26994fc3494adebf8512e66953b8f9fcb9','2026-09-07 13:37:00',0,'2026-08-08 13:37:00'),(98,100,'d239660faff68d9c50fda1988f26957a4a714d04be63ddf1','2026-09-07 16:50:37',0,'2026-08-08 16:50:37'),(99,101,'3d0e503dd99f09972a6072272febd413ad861df88de27513','2026-09-07 16:50:42',0,'2026-08-08 16:50:42'),(100,102,'d1e05201a9ac901bffab3b3f1b20bb35da36ebd3bf182d2c','2026-09-07 16:50:51',0,'2026-08-08 16:50:51'),(101,103,'d5067d2ce0b9893e56ce4acbfb6abf3d235f18e8f29e0c84','2026-09-07 16:50:56',0,'2026-08-08 16:50:56'),(102,104,'a72dbdfee762aa3c62ac09a3b6345e58c718792316c854fc','2026-09-07 16:51:02',0,'2026-08-08 16:51:02'),(103,105,'53f8c73ec382f0a16e17acd676d9ce9b9172314d598eba55','2026-09-07 16:51:07',0,'2026-08-08 16:51:07'),(104,106,'3599e575d937faaeb6687573ee19daa959dc3279561fe289','2026-09-07 16:51:12',0,'2026-08-08 16:51:12'),(105,107,'c730f03bc5ed3eb438760ce885da14b5e44e8f51b32fe4ea','2026-09-07 16:51:23',0,'2026-08-08 16:51:23'),(106,108,'fd62758a6063421bee490132dc8fd1a87c09c60c858dcc1c','2026-09-07 16:51:29',0,'2026-08-08 16:51:29'),(107,109,'0f0ae950d98ce531ce1a323c185a32d69d02e198a3f9e55f','2026-09-07 16:51:34',0,'2026-08-08 16:51:34'),(108,110,'c44e6890edfee46b0938fb75b98f8882f060f493e92ac73a','2026-09-07 16:51:39',0,'2026-08-08 16:51:39'),(109,111,'2977fd6ad625fc02113d6caba1ba0d6443da48523649b710','2026-09-07 16:51:44',0,'2026-08-08 16:51:44'),(110,112,'869309ac70b2aa36f24a4e0311b22328f6c5be15358f789b','2026-09-07 16:51:58',0,'2026-08-08 16:51:58'),(111,113,'8a1ce977b60f070d94842ac8303e90fdbb6045aaff37f2b6','2026-09-07 16:52:04',0,'2026-08-08 16:52:04'),(112,114,'fdabeab3ec0ffc3644baaa56592a07ff2c55b72ae6d9f5e5','2026-09-07 16:52:09',0,'2026-08-08 16:52:09'),(113,115,'9e182c6241c45f1454db149de05e92840eab348b75744f33','2026-09-07 16:52:14',0,'2026-08-08 16:52:14'),(114,116,'f56d04322fb10676383c466496a3706263f720eddfd5e5dc','2026-09-07 16:52:34',0,'2026-08-08 16:52:34'),(115,117,'3ebe429a3e7c625ee8dacef93cc8f330291e1d3fffea182a','2026-09-07 16:52:39',0,'2026-08-08 16:52:39'),(116,118,'133e672a80590faefb5a1aca9b1e6b4b94186924bade5d8a','2026-09-07 16:52:45',0,'2026-08-08 16:52:45'),(117,119,'32253c7aaa49f2a6312e7f5271a607047fdda547ca464626','2026-09-07 16:52:57',0,'2026-08-08 16:52:57'),(118,120,'df0b713e342bcafb3d3939e7603c49652db6626227a4f88a','2026-09-07 16:53:07',0,'2026-08-08 16:53:07'),(119,121,'99a5db23cd338818e8ede2f5682b7f70bb47e0fb59803f23','2026-09-07 16:53:11',0,'2026-08-08 16:53:11'),(120,122,'7176738eb3c463d60c76829c4a81aa1778e70aae14dd1f2a','2026-09-07 16:53:16',0,'2026-08-08 16:53:16'),(121,123,'4480a5c323f9a47c3074c23ed2b30950c59504c6619ba26c','2026-09-07 16:53:21',0,'2026-08-08 16:53:21'),(122,124,'434cfe3619d33b914a55251dcc15e7ed2a5c5a57f3733912','2026-09-07 16:53:26',0,'2026-08-08 16:53:26'),(123,125,'8efc8b289d53a5c41bf8d64fe57326e446b335014a5dc60c','2026-09-07 16:53:31',0,'2026-08-08 16:53:31'),(124,126,'8e1b189ba66ebc397c04d21e6de57e479718550a47ea55b7','2026-09-07 16:53:36',0,'2026-08-08 16:53:36'),(125,127,'337bcbd18665895299bc451eeeeeb8dd74b8431c0e03c822','2026-09-07 16:53:40',0,'2026-08-08 16:53:40'),(126,128,'14adac7d5288fbc5edf254565f225403da56e309473ff44e','2026-09-07 16:53:45',0,'2026-08-08 16:53:45'),(127,129,'93788f9f00dde775db8cb0ddbe236525d669e688e3ec3d52','2026-09-07 16:53:51',0,'2026-08-08 16:53:51'),(128,130,'575827def262a184cc794b7f778d12885c305cda1952701c','2026-09-07 16:53:55',0,'2026-08-08 16:53:55'),(129,131,'9b7627d80b1bc4c3154d78658ed7e690914f8475529f66e3','2026-09-07 16:54:02',0,'2026-08-08 16:54:02'),(130,132,'98d72c2d039f237749c8f1cb6cec548f81a4a5723fd43522','2026-09-07 16:54:07',0,'2026-08-08 16:54:07'),(131,133,'23f82fe75e9a5c6b2df035871dae57fede48911ec6e6d744','2026-09-07 16:54:12',0,'2026-08-08 16:54:12'),(132,134,'2f742da83e87a62699b24d7099ba376bd23bdee4e6bba5d7','2026-09-07 16:54:17',0,'2026-08-08 16:54:17'),(133,135,'984f2d9c1f9c7b66c0ae01827774b8751b036fc79f0a9665','2026-09-07 16:54:22',0,'2026-08-08 16:54:22'),(134,136,'f5321222868878ab18de3a6df3bb8c8c6face25ea2c3c7c4','2026-09-07 16:54:27',0,'2026-08-08 16:54:27'),(135,137,'a61eb2ac1952ed9f3c8fde2d8c22f7c20a6b8cc38c60f31f','2026-09-07 16:54:32',0,'2026-08-08 16:54:32'),(136,138,'bf74aefc526aa0dbe1d7300911c2981f79f87640fa1b8834','2026-09-07 16:54:37',0,'2026-08-08 16:54:37'),(137,139,'ec7fe4ddf38e1371d666af0fff997d96920665581ebc2bd2','2026-09-07 16:54:42',0,'2026-08-08 16:54:42'),(138,140,'1f7c3a1d96c1bcb9346d370bcbc30c28287ae4c61f155f0b','2026-09-07 16:54:47',0,'2026-08-08 16:54:47'),(139,141,'7c50ac3358aaeb1ae8b003f6b8e2324438fac5cbe474d3c1','2026-09-07 16:54:52',0,'2026-08-08 16:54:52'),(140,142,'375577d7d9e03444ac42c7967f567fa99467a02c7bea839d','2026-09-07 16:54:57',0,'2026-08-08 16:54:57'),(141,143,'b19247f5052934c62b49675e9cf5e98b07ac11dcee26a02f','2026-09-07 16:55:07',0,'2026-08-08 16:55:07'),(142,144,'99e71d310a7c28aaf7d38c46dea5b87a3ccc1f71c66aaf0c','2026-09-07 16:55:12',0,'2026-08-08 16:55:12'),(143,145,'39034d7d8b80f9b3f75aed660c926316f1b9ba12fa74ac74','2026-09-07 16:55:18',0,'2026-08-08 16:55:18'),(144,146,'ef7723b24566b6f775b571f1f8f3969d4fdb49c03e760889','2026-09-07 16:55:23',0,'2026-08-08 16:55:23'),(145,147,'24936cfba86fe2aaf27b5b2dc6ff77e877ebdec977cf1367','2026-09-07 16:55:38',0,'2026-08-08 16:55:38'),(146,148,'a4a060e4e32afe1c960c1993f7c78a6559f088a5a4b2f56c','2026-09-07 16:55:43',0,'2026-08-08 16:55:43'),(147,149,'ca38851251f47973cf7f6d6a07739c091482f51097282b4c','2026-09-07 16:55:48',0,'2026-08-08 16:55:48'),(148,150,'57357e4504ad83ebde2fd32d4585c5dbf8ac2f1cf5890693','2026-09-07 16:55:53',0,'2026-08-08 16:55:53'),(149,151,'d013e1751b4f2d5d8a67979c6ab1ea63eeab50a4f7fabc01','2026-09-07 16:55:58',0,'2026-08-08 16:55:58'),(150,152,'39d0be299de4f6b249e72c34731794733e494506682d21f2','2026-09-07 16:56:02',0,'2026-08-08 16:56:02'),(151,153,'b1741d7ec20dd388b8633129e68ff4eb6a6021bbd7342f66','2026-09-07 16:56:07',0,'2026-08-08 16:56:07'),(152,154,'d84a653172148ed365fac58170f8bad858273ccdc72e7064','2026-09-07 16:56:13',0,'2026-08-08 16:56:13'),(153,155,'49b45f41261cb3193e7b0c97c20d3fb65dbb51bc781179d1','2026-09-07 16:56:17',0,'2026-08-08 16:56:17'),(154,156,'131e8cdd6a16f3c9d9a4ac611edc5d5702eade56d00d72c0','2026-09-07 16:56:22',0,'2026-08-08 16:56:22'),(155,157,'5d52b06894289540292060cfa01bcebacce3e98cdf627153','2026-09-07 16:56:27',0,'2026-08-08 16:56:27'),(156,158,'4cd452c5dcc81aaeb6707b22fd8d1090ae36945b0b2626cc','2026-09-07 16:56:32',0,'2026-08-08 16:56:32'),(157,159,'d8a161311769a2530cb5150baaff78adb77b9d7616f4a48c','2026-09-07 16:56:41',0,'2026-08-08 16:56:41'),(158,160,'f2353f5947ab59b6bcb1fae37ef6b37a1e88f20a2e9099e4','2026-09-07 16:56:46',0,'2026-08-08 16:56:46'),(159,161,'392172d987496351b58e69dc6ddab5675cece1de65e5144a','2026-09-07 16:56:51',0,'2026-08-08 16:56:51'),(160,162,'61580fc5f216826323e22cb959fac04c03baebf114cd8746','2026-09-07 16:56:56',0,'2026-08-08 16:56:56'),(161,163,'11ded768a2043aa2b87c4e3cc9ada4a194b6546182182299','2026-09-07 16:57:01',0,'2026-08-08 16:57:01'),(162,164,'9b5f1bf8d94afc36662ece590b21bae3436efabad8da9ba2','2026-09-07 16:57:06',0,'2026-08-08 16:57:06'),(163,165,'9f407689af91dd4d1daf32df110d2263db02b428ddf5b6d6','2026-09-07 16:57:10',0,'2026-08-08 16:57:10'),(164,166,'fd9dcec08d356a8a3f108e250980d1e4be85b2c19dfb2c71','2026-09-07 16:57:15',0,'2026-08-08 16:57:15'),(165,167,'1035f6a02d47dd87b53c13288f1257a979705de41237c9a8','2026-09-07 16:57:31',0,'2026-08-08 16:57:31'),(166,168,'88a80ac15f624d3f3752a3d7140733666f0a7df28df50b7b','2026-09-07 16:57:36',0,'2026-08-08 16:57:36'),(167,169,'e73060afabd4f1a0523291156621da16e032fdd00a1a0af2','2026-09-07 16:57:41',0,'2026-08-08 16:57:41'),(168,170,'aea1f753dd72ffab297bad1363dc1407d89c555573408fe9','2026-09-07 16:57:47',0,'2026-08-08 16:57:47'),(169,171,'88cd818e26739b4fe2122f10234d2ae9d73043f5aa8e335f','2026-09-07 16:57:52',0,'2026-08-08 16:57:52'),(170,172,'31bd55c9ec272de72f16751ceb39aaab5cc8828ac3cc8a9a','2026-09-07 16:58:16',0,'2026-08-08 16:58:16'),(171,173,'19aa3fc5ffaace6d0ce179fb87ced7e08f21f294c4382bfd','2026-09-07 16:58:21',0,'2026-08-08 16:58:21'),(172,174,'98b8726fe15f4ecca4ca5276374b84868a2f4ca50794e496','2026-09-09 16:31:24',0,'2026-08-10 16:31:24');
/*!40000 ALTER TABLE `token_cita` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `token_seguridad`
--

DROP TABLE IF EXISTS `token_seguridad`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `token_seguridad` (
  `id_token` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `tipo` varchar(20) NOT NULL,
  `codigo` varchar(10) NOT NULL,
  `expira_en` datetime NOT NULL,
  `usado` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `canal` varchar(20) NOT NULL DEFAULT 'EMAIL',
  `dato` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id_token`),
  KEY `idx_tok_usuario` (`id_usuario`,`tipo`),
  CONSTRAINT `fk_tok_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `token_seguridad`
--

LOCK TABLES `token_seguridad` WRITE;
/*!40000 ALTER TABLE `token_seguridad` DISABLE KEYS */;
/*!40000 ALTER TABLE `token_seguridad` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `turno_dia`
--

DROP TABLE IF EXISTS `turno_dia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `turno_dia` (
  `id_turno` int(10) unsigned NOT NULL,
  `dia_semana` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id_turno`,`dia_semana`),
  CONSTRAINT `fk_turnodia_turno` FOREIGN KEY (`id_turno`) REFERENCES `turno_laboral` (`id_turno`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_turnodia_dia` CHECK (`dia_semana` between 1 and 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `turno_dia`
--

LOCK TABLES `turno_dia` WRITE;
/*!40000 ALTER TABLE `turno_dia` DISABLE KEYS */;
INSERT INTO `turno_dia` VALUES (3,1),(3,2),(3,3),(3,4),(3,5),(3,6),(4,1),(4,2),(4,3),(4,4),(4,5),(4,6);
/*!40000 ALTER TABLE `turno_dia` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `turno_laboral`
--

DROP TABLE IF EXISTS `turno_laboral`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `turno_laboral` (
  `id_turno` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_sucursal` int(10) unsigned NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fin` time NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_turno`),
  UNIQUE KEY `uq_turno_nombre` (`id_sucursal`,`nombre`),
  KEY `idx_turno_sucursal` (`id_sucursal`),
  CONSTRAINT `fk_turno_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursal` (`id_sucursal`) ON UPDATE CASCADE,
  CONSTRAINT `chk_turno_horas` CHECK (`hora_fin` > `hora_inicio`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `turno_laboral`
--

LOCK TABLES `turno_laboral` WRITE;
/*!40000 ALTER TABLE `turno_laboral` DISABLE KEYS */;
INSERT INTO `turno_laboral` VALUES (3,1,'Turno Mañana','08:00:00','12:30:00',1),(4,1,'Turno Tarde','13:30:00','19:00:00',1);
/*!40000 ALTER TABLE `turno_laboral` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario` (
  `id_usuario` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_rol` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned DEFAULT NULL,
  `username` varchar(60) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `fecha_ingreso` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `id_persona` int(10) unsigned NOT NULL,
  `sesion_activa` varchar(64) DEFAULT NULL COMMENT 'Id de la unica sesion abierta; al entrar de nuevo se reemplaza',
  `sesion_desde` datetime DEFAULT NULL COMMENT 'Cuando se abrio esa sesion, para poder decirlo en el aviso',
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `uq_usuario_username` (`username`),
  KEY `idx_usuario_rol` (`id_rol`),
  KEY `idx_usuario_sucursal` (`id_sucursal`),
  KEY `fk_usua_persona` (`id_persona`),
  CONSTRAINT `fk_usua_persona` FOREIGN KEY (`id_persona`) REFERENCES `persona` (`id_persona`) ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`id_rol`) REFERENCES `rol` (`id_rol`) ON UPDATE CASCADE,
  CONSTRAINT `fk_usuario_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursal` (`id_sucursal`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=61 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (1,1,1,'admin','$2y$10$aXqyrTtSHIcE7N.sPEA6xuI64h/JOM0/5frSbU5CuVp3qlQypgxgW','2026-07-14',1,'2026-07-14 19:42:29',1,'921046c49229f0682152eafe4201f0b4','2026-08-17 09:14:05'),(2,4,NULL,'cliente','$2y$10$pvpffhAjH9z6rqJfpekCSuwC.eFtu/j3iV883ICFOeZzeStA9P4DG',NULL,1,'2026-07-14 19:42:29',2,NULL,NULL),(6,4,NULL,'pablo','$2y$10$GynuBhOLHlSv.Cg9DWl8Gu8.jcdy4NJ6Uex0o1h392dGh5TPHDhDG',NULL,1,'2026-07-18 21:58:28',3,NULL,NULL),(8,2,1,'lucia','$2y$12$rqZBLjJbyvdDNJkGTieXW.ciyGudEmxfBm4Qy3fFG0pDxXxTPzg2m',NULL,1,'2026-08-08 13:00:33',5,NULL,NULL),(9,2,1,'marta','$2y$12$rqZBLjJbyvdDNJkGTieXW.ciyGudEmxfBm4Qy3fFG0pDxXxTPzg2m',NULL,1,'2026-08-08 13:00:33',6,NULL,NULL),(10,2,1,'rocio','$2y$12$rqZBLjJbyvdDNJkGTieXW.ciyGudEmxfBm4Qy3fFG0pDxXxTPzg2m',NULL,1,'2026-08-08 13:00:34',7,NULL,NULL),(11,2,1,'sofia','$2y$12$rqZBLjJbyvdDNJkGTieXW.ciyGudEmxfBm4Qy3fFG0pDxXxTPzg2m',NULL,1,'2026-08-08 13:00:34',8,NULL,NULL),(12,3,1,'carmen','$2y$10$hb9h4SNeWOxjmQK7/sieUuG/iQF9wHyNCtVecDo6dDKB/wIKh1EBy',NULL,1,'2026-08-08 13:00:35',9,NULL,NULL),(13,6,1,'gloria','$2y$10$FNGHw7B59EPCRuJPHvtRzeJZRHzWb/e/WKMty6y7K89kfuDL8Vexi',NULL,1,'2026-08-08 13:00:35',10,NULL,NULL);
/*!40000 ALTER TABLE `usuario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario_servicio`
--

DROP TABLE IF EXISTS `usuario_servicio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario_servicio` (
  `id_usuario_servicio` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_usuario` int(10) unsigned NOT NULL,
  `id_servicio` int(10) unsigned NOT NULL,
  `duracion_min` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_usuario_servicio`),
  UNIQUE KEY `uq_usuario_servicio` (`id_usuario`,`id_servicio`),
  KEY `idx_us_servicio` (`id_servicio`),
  CONSTRAINT `fk_us_servicio` FOREIGN KEY (`id_servicio`) REFERENCES `servicio` (`id_servicio`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_us_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_us_duracion` CHECK (`duracion_min` is null or `duracion_min` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_servicio`
--

LOCK TABLES `usuario_servicio` WRITE;
/*!40000 ALTER TABLE `usuario_servicio` DISABLE KEYS */;
/*!40000 ALTER TABLE `usuario_servicio` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario_sucursal`
--

DROP TABLE IF EXISTS `usuario_sucursal`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario_sucursal` (
  `id_usuario` int(10) unsigned NOT NULL,
  `id_sucursal` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_usuario`,`id_sucursal`),
  KEY `idx_usuc_sucursal` (`id_sucursal`),
  CONSTRAINT `fk_usuc_sucursal` FOREIGN KEY (`id_sucursal`) REFERENCES `sucursal` (`id_sucursal`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_usuc_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_sucursal`
--

LOCK TABLES `usuario_sucursal` WRITE;
/*!40000 ALTER TABLE `usuario_sucursal` DISABLE KEYS */;
INSERT INTO `usuario_sucursal` VALUES (1,1),(8,1),(9,1),(10,1),(11,1),(12,1),(13,1);
/*!40000 ALTER TABLE `usuario_sucursal` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuario_turno`
--

DROP TABLE IF EXISTS `usuario_turno`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario_turno` (
  `id_usuario` int(10) unsigned NOT NULL,
  `id_turno` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id_usuario`,`id_turno`),
  KEY `idx_usuturno_turno` (`id_turno`),
  CONSTRAINT `fk_usuturno_turno` FOREIGN KEY (`id_turno`) REFERENCES `turno_laboral` (`id_turno`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_usuturno_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario_turno`
--

LOCK TABLES `usuario_turno` WRITE;
/*!40000 ALTER TABLE `usuario_turno` DISABLE KEYS */;
INSERT INTO `usuario_turno` VALUES (8,3),(9,4),(10,3),(10,4),(11,4),(12,3),(12,4),(13,3);
/*!40000 ALTER TABLE `usuario_turno` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Temporary table structure for view `vw_agenda_bloqueos`
--

DROP TABLE IF EXISTS `vw_agenda_bloqueos`;
/*!50001 DROP VIEW IF EXISTS `vw_agenda_bloqueos`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_agenda_bloqueos` AS SELECT
 1 AS `id_ausencia`,
  1 AS `alcance`,
  1 AS `tipo`,
  1 AS `fecha_inicio`,
  1 AS `fecha_fin`,
  1 AS `motivo` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_agenda_citas`
--

DROP TABLE IF EXISTS `vw_agenda_citas`;
/*!50001 DROP VIEW IF EXISTS `vw_agenda_citas`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_agenda_citas` AS SELECT
 1 AS `id_cita`,
  1 AS `fecha_hora`,
  1 AS `duracion_min`,
  1 AS `cliente`,
  1 AS `telefono`,
  1 AS `profesional`,
  1 AS `estado`,
  1 AS `servicios`,
  1 AS `observaciones` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_caja_resumen`
--

DROP TABLE IF EXISTS `vw_caja_resumen`;
/*!50001 DROP VIEW IF EXISTS `vw_caja_resumen`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_caja_resumen` AS SELECT
 1 AS `id_sucursal`,
  1 AS `id_caja`,
  1 AS `responsable`,
  1 AS `estado`,
  1 AS `fecha_apertura`,
  1 AS `fecha_cierre`,
  1 AS `monto_inicial`,
  1 AS `cobros_efectivo`,
  1 AS `cobros_otros`,
  1 AS `cobros`,
  1 AS `otros_ingresos`,
  1 AS `egresos`,
  1 AS `pagos_prov_efectivo`,
  1 AS `pagos_prov_otros`,
  1 AS `pagos_proveedor`,
  1 AS `pagos_pers_efectivo`,
  1 AS `pagos_pers_otros`,
  1 AS `pagos_personal`,
  1 AS `saldo` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_cliente_fidelizacion`
--

DROP TABLE IF EXISTS `vw_cliente_fidelizacion`;
/*!50001 DROP VIEW IF EXISTS `vw_cliente_fidelizacion`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_cliente_fidelizacion` AS SELECT
 1 AS `id_cliente`,
  1 AS `cliente`,
  1 AS `telefono`,
  1 AS `visitas`,
  1 AS `puntos`,
  1 AS `nivel`,
  1 AS `descuento_del_nivel` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_compra_resumen`
--

DROP TABLE IF EXISTS `vw_compra_resumen`;
/*!50001 DROP VIEW IF EXISTS `vw_compra_resumen`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_compra_resumen` AS SELECT
 1 AS `id_compra`,
  1 AS `fecha`,
  1 AS `proveedor`,
  1 AS `registro`,
  1 AS `estado`,
  1 AS `total`,
  1 AS `observaciones` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_cuenta_proveedor`
--

DROP TABLE IF EXISTS `vw_cuenta_proveedor`;
/*!50001 DROP VIEW IF EXISTS `vw_cuenta_proveedor`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_cuenta_proveedor` AS SELECT
 1 AS `id_compra`,
  1 AS `id_proveedor`,
  1 AS `proveedor`,
  1 AS `fecha`,
  1 AS `nro_factura_proveedor`,
  1 AS `condicion`,
  1 AS `vencimiento`,
  1 AS `total`,
  1 AS `pagado`,
  1 AS `saldo`,
  1 AS `vencida` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_demanda_por_hora`
--

DROP TABLE IF EXISTS `vw_demanda_por_hora`;
/*!50001 DROP VIEW IF EXISTS `vw_demanda_por_hora`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_demanda_por_hora` AS SELECT
 1 AS `hora`,
  1 AS `citas`,
  1 AS `atendidas`,
  1 AS `ausencias`,
  1 AS `canceladas` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_detalle_factura`
--

DROP TABLE IF EXISTS `vw_detalle_factura`;
/*!50001 DROP VIEW IF EXISTS `vw_detalle_factura`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_detalle_factura` AS SELECT
 1 AS `id_detalle_factura`,
  1 AS `id_factura`,
  1 AS `item`,
  1 AS `clase`,
  1 AS `cantidad`,
  1 AS `precio_unitario`,
  1 AS `tasa_iva`,
  1 AS `subtotal` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_factura_impuestos`
--

DROP TABLE IF EXISTS `vw_factura_impuestos`;
/*!50001 DROP VIEW IF EXISTS `vw_factura_impuestos`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_factura_impuestos` AS SELECT
 1 AS `id_factura`,
  1 AS `nro_comprobante`,
  1 AS `tipo_comprobante`,
  1 AS `signo`,
  1 AS `gravado_10`,
  1 AS `iva_10`,
  1 AS `gravado_5`,
  1 AS `iva_5`,
  1 AS `exentas`,
  1 AS `total_comprobante` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_factura_resumen`
--

DROP TABLE IF EXISTS `vw_factura_resumen`;
/*!50001 DROP VIEW IF EXISTS `vw_factura_resumen`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_factura_resumen` AS SELECT
 1 AS `id_factura`,
  1 AS `fecha_emision`,
  1 AS `nro_comprobante`,
  1 AS `tipo_comprobante`,
  1 AS `signo`,
  1 AS `cliente`,
  1 AS `condicion_venta`,
  1 AS `fecha_vencimiento`,
  1 AS `estado`,
  1 AS `subtotal`,
  1 AS `descuento_total`,
  1 AS `total`,
  1 AS `total_neto`,
  1 AS `cobrado`,
  1 AS `saldo`,
  1 AS `comprobante_origen` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_habilitacion_profesional`
--

DROP TABLE IF EXISTS `vw_habilitacion_profesional`;
/*!50001 DROP VIEW IF EXISTS `vw_habilitacion_profesional`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_habilitacion_profesional` AS SELECT
 1 AS `profesional`,
  1 AS `servicio`,
  1 AS `categoria`,
  1 AS `duracion_min`,
  1 AS `precio`,
  1 AS `comision_vigente` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_historial_cliente`
--

DROP TABLE IF EXISTS `vw_historial_cliente`;
/*!50001 DROP VIEW IF EXISTS `vw_historial_cliente`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_historial_cliente` AS SELECT
 1 AS `id_cliente`,
  1 AS `cliente`,
  1 AS `id_cita`,
  1 AS `fecha_hora`,
  1 AS `servicio`,
  1 AS `precio`,
  1 AS `profesional`,
  1 AS `nro_comprobante`,
  1 AS `puntaje` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_pago_personal_resumen`
--

DROP TABLE IF EXISTS `vw_pago_personal_resumen`;
/*!50001 DROP VIEW IF EXISTS `vw_pago_personal_resumen`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_pago_personal_resumen` AS SELECT
 1 AS `id_pago_personal`,
  1 AS `fecha`,
  1 AS `periodo`,
  1 AS `beneficiario`,
  1 AS `estado`,
  1 AS `servicios`,
  1 AS `monto` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_producto_bajo_stock`
--

DROP TABLE IF EXISTS `vw_producto_bajo_stock`;
/*!50001 DROP VIEW IF EXISTS `vw_producto_bajo_stock`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_producto_bajo_stock` AS SELECT
 1 AS `id_producto`,
  1 AS `id_sucursal`,
  1 AS `nombre`,
  1 AS `categoria`,
  1 AS `stock_actual`,
  1 AS `stock_minimo`,
  1 AS `faltante`,
  1 AS `precio_costo` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_producto_stock`
--

DROP TABLE IF EXISTS `vw_producto_stock`;
/*!50001 DROP VIEW IF EXISTS `vw_producto_stock`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_producto_stock` AS SELECT
 1 AS `id_producto`,
  1 AS `id_sucursal`,
  1 AS `nombre`,
  1 AS `categoria`,
  1 AS `unidad_medida`,
  1 AS `stock_actual`,
  1 AS `stock_minimo`,
  1 AS `precio_costo`,
  1 AS `precio_venta`,
  1 AS `activo` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_servicios_mas_solicitados`
--

DROP TABLE IF EXISTS `vw_servicios_mas_solicitados`;
/*!50001 DROP VIEW IF EXISTS `vw_servicios_mas_solicitados`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_servicios_mas_solicitados` AS SELECT
 1 AS `id_servicio`,
  1 AS `servicio`,
  1 AS `categoria`,
  1 AS `veces_realizado`,
  1 AS `ingreso_generado` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_servicios_por_profesional`
--

DROP TABLE IF EXISTS `vw_servicios_por_profesional`;
/*!50001 DROP VIEW IF EXISTS `vw_servicios_por_profesional`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_servicios_por_profesional` AS SELECT
 1 AS `id_servicio_realizado`,
  1 AS `fecha_hora`,
  1 AS `profesional`,
  1 AS `servicio`,
  1 AS `precio`,
  1 AS `pagado` */;
SET character_set_client = @saved_cs_client;

--
-- Dumping events for database 'peluqueria_test'
--

--
-- Dumping routines for database 'peluqueria_test'
--
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_caja_saldo` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_caja_saldo`(p_id_caja INT UNSIGNED) RETURNS decimal(14,2)
    READS SQL DATA
BEGIN
  DECLARE v_inicial DECIMAL(14,2) DEFAULT 0;
  DECLARE v_cobros  DECIMAL(14,2) DEFAULT 0;
  DECLARE v_ing     DECIMAL(14,2) DEFAULT 0;
  DECLARE v_egr     DECIMAL(14,2) DEFAULT 0;
  DECLARE v_prov    DECIMAL(14,2) DEFAULT 0;
  DECLARE v_pers    DECIMAL(14,2) DEFAULT 0;

  SELECT monto_inicial INTO v_inicial FROM caja WHERE id_caja = p_id_caja;

  SELECT COALESCE(SUM(co.monto), 0) INTO v_cobros
  FROM cobro co
  JOIN metodo_pago mp ON mp.id_metodo_pago = co.id_metodo_pago
  WHERE co.id_caja = p_id_caja AND co.id_estado_cobro = 1
    AND mp.tipo = 'EFECTIVO';

  SELECT COALESCE(SUM(CASE WHEN tipo = 'INGRESO' THEN monto END), 0),
         COALESCE(SUM(CASE WHEN tipo = 'EGRESO'  THEN monto END), 0)
    INTO v_ing, v_egr
  FROM movimiento_caja WHERE id_caja = p_id_caja;

  SELECT COALESCE(SUM(fn_pago_proveedor_monto(pp.id_pago_proveedor)), 0) INTO v_prov
  FROM pago_proveedor pp
  JOIN metodo_pago mp ON mp.id_metodo_pago = pp.id_metodo_pago
  WHERE pp.id_caja = p_id_caja AND pp.id_estado_pago_proveedor = 1
    AND mp.tipo = 'EFECTIVO';

  
  
  SELECT COALESCE(SUM(fn_pago_personal_monto(pg.id_pago_personal)), 0) INTO v_pers
  FROM pago_personal pg
  JOIN metodo_pago mp ON mp.id_metodo_pago = pg.id_metodo_pago
  WHERE pg.id_caja = p_id_caja AND pg.id_estado_pago = 1
    AND mp.tipo = 'EFECTIVO';

  RETURN COALESCE(v_inicial, 0) + v_cobros + v_ing - v_egr - v_prov - v_pers;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_canje_estado` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_canje_estado`(p_id_canje INT UNSIGNED) RETURNS varchar(12) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
    READS SQL DATA
BEGIN
  DECLARE v_cita  INT UNSIGNED DEFAULT NULL;
  DECLARE v_vence DATE DEFAULT NULL;

  SELECT id_cita, vence_en INTO v_cita, v_vence FROM canje WHERE id_canje = p_id_canje;

  IF v_vence IS NULL THEN RETURN 'INEXISTENTE'; END IF;
  IF v_cita IS NOT NULL THEN RETURN 'USADO'; END IF;
  IF v_vence < CURDATE() THEN RETURN 'VENCIDO'; END IF;

  RETURN 'DISPONIBLE';
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_cita_duracion` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_cita_duracion`(p_id_cita INT UNSIGNED) RETURNS int(11)
    READS SQL DATA
    DETERMINISTIC
BEGIN
              DECLARE v_dur INT DEFAULT 0;
              SELECT COALESCE(MAX(bloque), 0) INTO v_dur FROM (
                SELECT SUM(COALESCE(us.duracion_min, s.duracion_min)) AS bloque
                  FROM cita_servicio cs
                  JOIN cita c     ON c.id_cita = cs.id_cita
                  JOIN servicio s ON s.id_servicio = cs.id_servicio
                  LEFT JOIN usuario_servicio us
                         ON us.id_usuario = COALESCE(cs.id_usuario, c.id_usuario)
                        AND us.id_servicio = s.id_servicio AND us.activo = 1
                 WHERE cs.id_cita = p_id_cita
                 GROUP BY COALESCE(cs.id_usuario, c.id_usuario)
              ) bloques;
              RETURN IF(v_dur > 0, v_dur, 60);
            END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_cita_duracion_de` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_cita_duracion_de`(p_id_cita INT UNSIGNED, p_id_usuario INT UNSIGNED) RETURNS int(11)
    READS SQL DATA
    DETERMINISTIC
BEGIN
              DECLARE v_dur INT DEFAULT 0;
              SELECT COALESCE(SUM(COALESCE(us.duracion_min, s.duracion_min)), 0) INTO v_dur
                FROM cita_servicio cs
                JOIN cita c     ON c.id_cita = cs.id_cita
                JOIN servicio s ON s.id_servicio = cs.id_servicio
                LEFT JOIN usuario_servicio us
                       ON us.id_usuario = p_id_usuario AND us.id_servicio = s.id_servicio AND us.activo = 1
               WHERE cs.id_cita = p_id_cita
                 AND COALESCE(cs.id_usuario, c.id_usuario) = p_id_usuario;
              RETURN v_dur;
            END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_cita_sena` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_cita_sena`(p_id_cita INT UNSIGNED) RETURNS decimal(14,2)
    READS SQL DATA
BEGIN
  DECLARE v DECIMAL(14,2) DEFAULT 0;
  SELECT COALESCE(SUM(monto), 0) INTO v
  FROM cobro WHERE id_cita = p_id_cita AND id_estado_cobro = 1;
  RETURN v;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_cliente_descuento` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_cliente_descuento`(p_id_cliente INT UNSIGNED) RETURNS int(10) unsigned
    READS SQL DATA
BEGIN
  DECLARE v_desc INT UNSIGNED DEFAULT NULL;
  SELECT id_descuento INTO v_desc
  FROM nivel WHERE id_nivel = fn_cliente_nivel(p_id_cliente);
  RETURN v_desc;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_cliente_nivel` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_cliente_nivel`(p_id_cliente INT UNSIGNED) RETURNS int(10) unsigned
    READS SQL DATA
BEGIN
  DECLARE v_nivel INT UNSIGNED DEFAULT NULL;
  SELECT id_nivel INTO v_nivel
  FROM nivel
  WHERE activo = 1 AND visitas_minimas <= fn_cliente_visitas(p_id_cliente)
  ORDER BY visitas_minimas DESC
  LIMIT 1;
  RETURN v_nivel;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_cliente_puntos` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_cliente_puntos`(p_id_cliente INT UNSIGNED) RETURNS int(11)
    READS SQL DATA
BEGIN
  DECLARE v_puntos INT DEFAULT 0;
  SELECT COALESCE(SUM(puntos), 0) INTO v_puntos
  FROM movimiento_punto WHERE id_cliente = p_id_cliente;
  RETURN v_puntos;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_cliente_visitas` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_cliente_visitas`(p_id_cliente INT UNSIGNED) RETURNS int(11)
    READS SQL DATA
BEGIN
  DECLARE v INT DEFAULT 0;
  SELECT COUNT(*) INTO v FROM cita
  WHERE id_cliente = p_id_cliente AND id_estado_cita = 4;
  RETURN v;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_comision_servicio` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_comision_servicio`(p_id_servicio_realizado INT UNSIGNED) RETURNS decimal(14,2)
    READS SQL DATA
BEGIN
  DECLARE v_usuario  INT UNSIGNED DEFAULT NULL;
  DECLARE v_servicio INT UNSIGNED DEFAULT NULL;
  DECLARE v_fecha    DATE;
  DECLARE v_precio   DECIMAL(12,2) DEFAULT 0;
  DECLARE v_tipo     VARCHAR(20)   DEFAULT NULL;
  DECLARE v_valor    DECIMAL(12,2) DEFAULT 0;

  SELECT sr.id_usuario, sr.id_servicio, DATE(sr.fecha_hora), s.precio
    INTO v_usuario, v_servicio, v_fecha, v_precio
  FROM servicio_realizado sr
  JOIN servicio s ON s.id_servicio = sr.id_servicio
  WHERE sr.id_servicio_realizado = p_id_servicio_realizado;

  IF v_usuario IS NULL THEN RETURN 0; END IF;

  SELECT c.tipo, c.valor INTO v_tipo, v_valor
  FROM comision c
  WHERE c.id_usuario = v_usuario
    AND (c.id_servicio = v_servicio OR c.id_servicio IS NULL)
    AND c.activo = 1
    AND c.vigente_desde <= v_fecha
  ORDER BY (c.id_servicio IS NULL) ASC, c.vigente_desde DESC
  LIMIT 1;

  IF v_tipo IS NULL THEN RETURN 0; END IF;
  IF v_tipo = 'PORCENTAJE' THEN
    RETURN ROUND(COALESCE(v_precio, 0) * v_valor / 100, 2);
  END IF;
  RETURN v_valor;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_compra_cuotas` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_compra_cuotas`(p_id_compra INT UNSIGNED) RETURNS smallint(5) unsigned
    READS SQL DATA
BEGIN
  DECLARE v_n SMALLINT UNSIGNED DEFAULT 0;
  SELECT COUNT(*) INTO v_n FROM compra_cuota WHERE id_compra = p_id_compra;
  RETURN v_n;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_compra_saldo` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_compra_saldo`(p_id_compra INT UNSIGNED) RETURNS decimal(14,2)
    READS SQL DATA
BEGIN
  DECLARE v_pagado DECIMAL(14,2) DEFAULT 0;
  SELECT COALESCE(SUM(d.monto_aplicado), 0) INTO v_pagado
  FROM detalle_pago_proveedor d
  JOIN pago_proveedor pp ON pp.id_pago_proveedor = d.id_pago_proveedor
  WHERE d.id_compra = p_id_compra AND pp.id_estado_pago_proveedor = 1;
  RETURN fn_compra_total(p_id_compra) - v_pagado;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_compra_total` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_compra_total`(p_id_compra INT UNSIGNED) RETURNS decimal(14,2)
    READS SQL DATA
BEGIN
  DECLARE v_total DECIMAL(14,2) DEFAULT 0;
  SELECT COALESCE(SUM(ROUND(cantidad * precio_unitario, 2)), 0) INTO v_total
  FROM detalle_compra WHERE id_compra = p_id_compra;
  RETURN v_total;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_compra_vencimiento` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_compra_vencimiento`(p_id_compra INT UNSIGNED) RETURNS date
    READS SQL DATA
BEGIN
  DECLARE v_venc DATE DEFAULT NULL;

  SELECT MIN(cu.fecha_vencimiento) INTO v_venc
  FROM compra_cuota cu WHERE cu.id_compra = p_id_compra;

  IF v_venc IS NOT NULL THEN
    RETURN v_venc;
  END IF;

  SELECT DATE(c.fecha) + INTERVAL cv.dias_credito DAY INTO v_venc
  FROM compra c
  JOIN condicion_venta cv ON cv.id_condicion_venta = c.id_condicion_venta
  WHERE c.id_compra = p_id_compra;

  RETURN v_venc;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_descuento_monto` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_descuento_monto`(p_id_descuento INT UNSIGNED, p_base DECIMAL(14,2)) RETURNS decimal(14,2)
    READS SQL DATA
BEGIN
  DECLARE v_tipo   VARCHAR(20);
  DECLARE v_valor  DECIMAL(10,2) DEFAULT 0;
  DECLARE v_activo TINYINT(1)    DEFAULT 0;
  DECLARE v_ini    DATE;
  DECLARE v_fin    DATE;
  DECLARE v_monto  DECIMAL(14,2) DEFAULT 0;

  SELECT tipo, valor, activo, fecha_inicio, fecha_fin
    INTO v_tipo, v_valor, v_activo, v_ini, v_fin
  FROM descuento WHERE id_descuento = p_id_descuento;

  IF v_activo <> 1 THEN RETURN 0; END IF;
  IF v_ini IS NOT NULL AND CURRENT_DATE < v_ini THEN RETURN 0; END IF;
  IF v_fin IS NOT NULL AND CURRENT_DATE > v_fin THEN RETURN 0; END IF;

  IF v_tipo = 'PORCENTAJE' THEN
    SET v_monto = ROUND(p_base * v_valor / 100, 2);
  ELSE
    SET v_monto = v_valor;
  END IF;

  IF v_monto > p_base THEN SET v_monto = p_base; END IF;
  IF v_monto < 0 THEN SET v_monto = 0; END IF;
  RETURN v_monto;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_descuento_monto_factura` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_descuento_monto_factura`(p_id_factura   INT UNSIGNED,
                p_id_descuento INT UNSIGNED
            ) RETURNS decimal(14,2)
    READS SQL DATA
    DETERMINISTIC
BEGIN
              DECLARE v_base        DECIMAL(14,2) DEFAULT 0;
              DECLARE v_restringido INT DEFAULT 0;

              IF p_id_descuento IS NULL THEN RETURN 0; END IF;

              SELECT COUNT(*) INTO v_restringido
              FROM servicio_descuento WHERE id_descuento = p_id_descuento;

              IF v_restringido > 0 THEN
                SELECT COALESCE(SUM(ROUND(df.cantidad * df.precio_unitario, 2)), 0) INTO v_base
                FROM detalle_factura df
                JOIN servicio_descuento sd
                  ON sd.id_servicio = df.id_servicio AND sd.id_descuento = p_id_descuento
                WHERE df.id_factura = p_id_factura;
              ELSE
                SET v_base = fn_factura_subtotal(p_id_factura);
              END IF;

              RETURN fn_descuento_monto(p_id_descuento, v_base);
            END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_es_personal` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_es_personal`(p_id_usuario INT UNSIGNED) RETURNS tinyint(1)
    READS SQL DATA
BEGIN
  DECLARE v_es TINYINT(1) DEFAULT 0;
  SELECT r.es_personal INTO v_es
  FROM usuario u JOIN rol r ON r.id_rol = u.id_rol
  WHERE u.id_usuario = p_id_usuario;
  RETURN COALESCE(v_es, 0);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_factura_descuento` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_factura_descuento`(p_id_factura INT UNSIGNED) RETURNS decimal(14,2)
    READS SQL DATA
BEGIN
  DECLARE v DECIMAL(14,2) DEFAULT 0;
  SELECT COALESCE(SUM(monto_aplicado), 0) INTO v
  FROM factura_descuento WHERE id_factura = p_id_factura;
  RETURN v;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_factura_nro` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_factura_nro`(p_id_factura INT UNSIGNED) RETURNS varchar(20) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
    READS SQL DATA
BEGIN
  DECLARE v_nro VARCHAR(20) DEFAULT NULL;
  SELECT CONCAT(t.establecimiento, '-', t.punto_expedicion, '-', LPAD(f.nro_correlativo, 7, '0'))
    INTO v_nro
  FROM factura f JOIN timbrado t ON t.id_timbrado = f.id_timbrado
  WHERE f.id_factura = p_id_factura;
  RETURN v_nro;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_factura_saldo` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_factura_saldo`(p_id_factura INT UNSIGNED) RETURNS decimal(14,2)
    READS SQL DATA
BEGIN
  DECLARE v_cobrado DECIMAL(14,2) DEFAULT 0;
  DECLARE v_sena    DECIMAL(14,2) DEFAULT 0;

  SELECT COALESCE(SUM(monto), 0) INTO v_cobrado
  FROM cobro WHERE id_factura = p_id_factura AND id_estado_cobro = 1;

  
  SELECT COALESCE(SUM(co.monto), 0) INTO v_sena
  FROM factura f
  JOIN cobro co ON co.id_cita = f.id_cita AND co.id_estado_cobro = 1
  WHERE f.id_factura = p_id_factura AND f.id_cita IS NOT NULL;

  RETURN fn_factura_total(p_id_factura) - v_cobrado - v_sena;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_factura_subtotal` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_factura_subtotal`(p_id_factura INT UNSIGNED) RETURNS decimal(14,2)
    READS SQL DATA
BEGIN
  DECLARE v DECIMAL(14,2) DEFAULT 0;
  SELECT COALESCE(SUM(ROUND(cantidad * precio_unitario, 2)), 0) INTO v
  FROM detalle_factura WHERE id_factura = p_id_factura;
  RETURN v;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_factura_total` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_factura_total`(p_id_factura INT UNSIGNED) RETURNS decimal(14,2)
    READS SQL DATA
BEGIN
  RETURN GREATEST(fn_factura_subtotal(p_id_factura) - fn_factura_descuento(p_id_factura), 0);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_factura_vencimiento` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_factura_vencimiento`(p_id_factura INT UNSIGNED) RETURNS date
    READS SQL DATA
BEGIN
  DECLARE v_venc DATE DEFAULT NULL;
  SELECT DATE(f.fecha_emision) + INTERVAL cv.dias_credito DAY INTO v_venc
  FROM factura f
  JOIN condicion_venta cv ON cv.id_condicion_venta = f.id_condicion_venta
  WHERE f.id_factura = p_id_factura;
  RETURN v_venc;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_pago_personal_monto` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_pago_personal_monto`(p_id_pago INT UNSIGNED) RETURNS decimal(14,2)
    READS SQL DATA
BEGIN
  DECLARE v DECIMAL(14,2) DEFAULT 0;
  SELECT COALESCE(SUM(monto), 0) INTO v
  FROM detalle_pago_personal WHERE id_pago_personal = p_id_pago;
  RETURN v;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_pago_proveedor_monto` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_pago_proveedor_monto`(p_id_pago INT UNSIGNED) RETURNS decimal(14,2)
    READS SQL DATA
BEGIN
  DECLARE v DECIMAL(14,2) DEFAULT 0;
  SELECT COALESCE(SUM(monto_aplicado), 0) INTO v
  FROM detalle_pago_proveedor WHERE id_pago_proveedor = p_id_pago;
  RETURN v;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_producto_stock` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_producto_stock`(p_id_producto INT UNSIGNED, p_id_sucursal INT UNSIGNED) RETURNS decimal(12,4)
    READS SQL DATA
BEGIN
  DECLARE v_stock DECIMAL(12,4) DEFAULT 0;
  SELECT COALESCE(SUM(CASE WHEN t.signo = 'E' THEN m.cantidad ELSE -m.cantidad END), 0)
    INTO v_stock
  FROM movimiento_inventario m
  JOIN tipo_movimiento_inventario t ON t.id_tipo_movimiento = m.id_tipo_movimiento
  WHERE m.id_producto = p_id_producto
    AND m.id_sucursal = p_id_sucursal;
  RETURN v_stock;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_promo_vigente` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_promo_vigente`(p_id_factura INT UNSIGNED) RETURNS int(10) unsigned
    READS SQL DATA
    DETERMINISTIC
BEGIN
              DECLARE v_id INT UNSIGNED DEFAULT NULL;

              SELECT d.id_descuento INTO v_id
              FROM descuento d
              WHERE d.activo = 1
                AND NOT EXISTS (SELECT 1 FROM nivel n WHERE n.id_descuento = d.id_descuento)
                AND fn_descuento_monto_factura(p_id_factura, d.id_descuento) > 0
              ORDER BY fn_descuento_monto_factura(p_id_factura, d.id_descuento) DESC,
                       d.id_descuento ASC
              LIMIT 1;

              RETURN v_id;
            END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_proveedor_saldo` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_proveedor_saldo`(p_id_proveedor INT UNSIGNED) RETURNS decimal(14,2)
    READS SQL DATA
BEGIN
  DECLARE v DECIMAL(14,2) DEFAULT 0;
  SELECT COALESCE(SUM(fn_compra_saldo(c.id_compra)), 0) INTO v
  FROM compra c
  WHERE c.id_proveedor = p_id_proveedor AND c.id_estado_compra = 2;
  RETURN v;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_puede_realizar` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_puede_realizar`(p_id_usuario INT UNSIGNED, p_id_servicio INT UNSIGNED) RETURNS tinyint(1)
    READS SQL DATA
BEGIN
  DECLARE v_cargadas INT DEFAULT 0;
  DECLARE v_hab      INT DEFAULT 0;

  SELECT COUNT(*) INTO v_cargadas FROM usuario_servicio
   WHERE id_usuario = p_id_usuario AND activo = 1;
  IF v_cargadas = 0 THEN RETURN 1; END IF;

  SELECT COUNT(*) INTO v_hab FROM usuario_servicio
   WHERE id_usuario = p_id_usuario AND id_servicio = p_id_servicio AND activo = 1;
  RETURN IF(v_hab > 0, 1, 0);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_siguiente_correlativo` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_siguiente_correlativo`(p_id_timbrado INT UNSIGNED) RETURNS int(10) unsigned
    READS SQL DATA
BEGIN
  DECLARE v_desde  INT UNSIGNED DEFAULT 0;
  DECLARE v_hasta  INT UNSIGNED DEFAULT 0;
  DECLARE v_ultimo INT UNSIGNED DEFAULT 0;
  DECLARE v_sig    INT UNSIGNED DEFAULT 0;

  SELECT nro_desde, nro_hasta INTO v_desde, v_hasta
  FROM timbrado WHERE id_timbrado = p_id_timbrado;

  IF v_desde = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El timbrado indicado no existe.';
  END IF;

  SELECT COALESCE(MAX(nro_correlativo), 0) INTO v_ultimo
  FROM factura WHERE id_timbrado = p_id_timbrado;

  SET v_sig = IF(v_ultimo < v_desde, v_desde, v_ultimo + 1);

  IF v_sig > v_hasta THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El rango del timbrado esta agotado.';
  END IF;

  RETURN v_sig;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_timbrado_vigente` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_timbrado_vigente`(p_id_tipo_comprobante INT UNSIGNED, p_fecha DATE) RETURNS int(10) unsigned
    READS SQL DATA
BEGIN
  DECLARE v_id INT UNSIGNED DEFAULT NULL;
  SELECT t.id_timbrado INTO v_id
  FROM timbrado t
  WHERE t.id_tipo_comprobante = p_id_tipo_comprobante
    AND t.activo = 1
    AND p_fecha BETWEEN t.fecha_inicio AND t.fecha_fin
  ORDER BY t.fecha_fin ASC, t.id_timbrado ASC
  LIMIT 1;
  RETURN v_id;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP FUNCTION IF EXISTS `fn_verificar_disponibilidad` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `fn_verificar_disponibilidad`(p_id_usuario     INT,
    p_fecha_hora     DATETIME,
    p_duracion_min   INT,
    p_id_cita_excluir INT
) RETURNS tinyint(1)
    READS SQL DATA
BEGIN
  DECLARE v_conflictos INT DEFAULT 0;
  DECLARE v_turnos     INT DEFAULT 0;
  DECLARE v_salon      INT DEFAULT 0;
  DECLARE v_cubre      INT DEFAULT 0;
  DECLARE v_dur        INT DEFAULT 60;
  DECLARE v_fin        DATETIME;

  SET v_dur = IF(p_duracion_min IS NULL OR p_duracion_min <= 0, 60, p_duracion_min);
  SET v_fin = p_fecha_hora + INTERVAL v_dur MINUTE;

  
  IF EXISTS (SELECT 1 FROM ausencia_agenda a
              WHERE a.activo = 1
                AND (a.id_usuario = p_id_usuario OR a.id_usuario IS NULL)
                AND a.fecha_inicio < v_fin
                AND p_fecha_hora < a.fecha_fin) THEN
    RETURN 0;
  END IF;

  
  SELECT COUNT(*) INTO v_turnos
    FROM usuario_turno ut
    JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1
   WHERE ut.id_usuario = p_id_usuario;

  IF v_turnos = 0 THEN
    
    
    SELECT COUNT(*) INTO v_salon
      FROM usuario_turno ut
      JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1;
    IF v_salon > 0 THEN RETURN 0; END IF;
    
    
  ELSE
    SELECT COUNT(*) INTO v_cubre
      FROM usuario_turno ut
      JOIN turno_laboral t ON t.id_turno = ut.id_turno AND t.activo = 1
      JOIN turno_dia td    ON td.id_turno = t.id_turno
     WHERE ut.id_usuario = p_id_usuario
       AND td.dia_semana = WEEKDAY(p_fecha_hora) + 1
       AND TIME(p_fecha_hora) >= t.hora_inicio
       AND TIME(v_fin) <= t.hora_fin
       AND DATE(v_fin) = DATE(p_fecha_hora);
    IF v_cubre = 0 THEN RETURN 0; END IF;
  END IF;

  
  SELECT COUNT(*) INTO v_conflictos
    FROM cita c
    JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
   WHERE ec.bloquea_agenda = 1
     AND (p_id_cita_excluir IS NULL OR c.id_cita <> p_id_cita_excluir)
     AND (c.id_usuario = p_id_usuario
          OR EXISTS (SELECT 1 FROM cita_servicio cs
                      WHERE cs.id_cita = c.id_cita AND cs.id_usuario = p_id_usuario))
     AND fn_cita_duracion_de(c.id_cita, p_id_usuario) > 0
     AND c.fecha_hora < v_fin
     AND p_fecha_hora < (c.fecha_hora + INTERVAL fn_cita_duracion_de(c.id_cita, p_id_usuario) MINUTE);

  RETURN IF(v_conflictos = 0, 1, 0);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_abrir_caja` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_abrir_caja`(
    IN  p_id_usuario    INT UNSIGNED,
    IN  p_monto_inicial DECIMAL(14,2),
    IN  p_id_sucursal   INT UNSIGNED,
    OUT p_id_caja       INT UNSIGNED)
BEGIN
  INSERT INTO caja (id_usuario, id_sucursal, id_estado_caja, monto_inicial)
  VALUES (p_id_usuario, p_id_sucursal, 1, p_monto_inicial);
  SET p_id_caja = LAST_INSERT_ID();
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_agendar_cita` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = cp850 */ ;
/*!50003 SET character_set_results = cp850 */ ;
/*!50003 SET collation_connection  = cp850_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_agendar_cita`(
    IN  p_id_cliente    INT UNSIGNED,
    IN  p_id_usuario    INT UNSIGNED,
    IN  p_fecha_hora    DATETIME,
    IN  p_duracion_min  INT,
    IN  p_observaciones VARCHAR(300),
    IN  p_id_sucursal   INT UNSIGNED,
    OUT p_id_cita       INT UNSIGNED)
BEGIN
  DECLARE v_lock INT UNSIGNED;

  
  
  SELECT id_usuario INTO v_lock FROM usuario
   WHERE id_usuario = p_id_usuario FOR UPDATE;

  IF fn_verificar_disponibilidad(p_id_usuario, p_fecha_hora, p_duracion_min, NULL) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El profesional no esta disponible en ese horario.';
  END IF;

  
  
  IF NOT EXISTS (SELECT 1 FROM sucursal WHERE id_sucursal = p_id_sucursal AND activo = 1) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Esa sucursal no existe o esta dada de baja.';
  END IF;

  INSERT INTO cita (id_cliente, id_usuario, id_sucursal, id_estado_cita, fecha_hora, observaciones)
  VALUES (p_id_cliente, p_id_usuario, p_id_sucursal, 1, p_fecha_hora, p_observaciones);
  SET p_id_cita = LAST_INSERT_ID();

  INSERT INTO notificacion (id_tipo_notificacion, id_cliente, id_cita, canal, mensaje, estado)
  VALUES (2, p_id_cliente, p_id_cita, 'WHATSAPP',
          CONCAT('Cita confirmada para el ', DATE_FORMAT(p_fecha_hora, '%d/%m/%Y a las %H:%i'), '.'),
          'PENDIENTE');
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_anular_cobro` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_anular_cobro`(
    IN p_id_cobro   INT UNSIGNED,
    IN p_id_usuario INT UNSIGNED)
BEGIN
  DECLARE v_estado INT UNSIGNED DEFAULT NULL;

  SELECT id_estado_cobro INTO v_estado FROM cobro WHERE id_cobro = p_id_cobro;

  IF v_estado IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cobro no existe.';
  END IF;

  IF v_estado = 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El cobro ya estaba anulado.';
  END IF;

  SET @usuario_actual = p_id_usuario;
  UPDATE cobro SET id_estado_cobro = 3 WHERE id_cobro = p_id_cobro;
  SET @usuario_actual = NULL;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_anular_factura` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_anular_factura`(
    IN p_id_factura INT UNSIGNED,
    IN p_id_usuario INT UNSIGNED)
BEGIN
  DECLARE v_cobros INT DEFAULT 0;

  SELECT COUNT(*) INTO v_cobros FROM cobro
   WHERE id_factura = p_id_factura AND id_estado_cobro = 1;

  IF v_cobros > 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Anule primero los cobros de esta factura.';
  END IF;

  SET @usuario_actual = p_id_usuario;
  UPDATE factura SET id_estado_factura = 2 WHERE id_factura = p_id_factura;
  SET @usuario_actual = NULL;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_anular_pago_proveedor` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_anular_pago_proveedor`(
    IN p_id_pago    INT UNSIGNED,
    IN p_id_usuario INT UNSIGNED)
BEGIN
  DECLARE v_estado INT UNSIGNED DEFAULT NULL;

  SELECT id_estado_pago_proveedor INTO v_estado
  FROM pago_proveedor WHERE id_pago_proveedor = p_id_pago;

  IF v_estado IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El pago no existe.';
  END IF;

  IF v_estado = 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El pago ya estaba anulado.';
  END IF;

  SET @usuario_actual = p_id_usuario;
  UPDATE pago_proveedor SET id_estado_pago_proveedor = 2 WHERE id_pago_proveedor = p_id_pago;
  SET @usuario_actual = NULL;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_aplicar_descuento` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_aplicar_descuento`(
    IN p_id_factura   INT UNSIGNED,
    IN p_id_descuento INT UNSIGNED)
BEGIN
  DECLARE v_base        DECIMAL(14,2) DEFAULT 0;
  DECLARE v_monto       DECIMAL(14,2) DEFAULT 0;
  DECLARE v_restringido INT DEFAULT 0;

  SELECT COUNT(*) INTO v_restringido FROM servicio_descuento WHERE id_descuento = p_id_descuento;

  IF v_restringido > 0 THEN
    SELECT COALESCE(SUM(ROUND(df.cantidad * df.precio_unitario, 2)), 0) INTO v_base
    FROM detalle_factura df
    JOIN servicio_descuento sd ON sd.id_servicio = df.id_servicio AND sd.id_descuento = p_id_descuento
    WHERE df.id_factura = p_id_factura;
  ELSE
    SET v_base = fn_factura_subtotal(p_id_factura);
  END IF;

  SET v_monto = fn_descuento_monto(p_id_descuento, v_base);

  IF v_monto > 0 THEN
    INSERT INTO factura_descuento (id_factura, id_descuento, monto_aplicado)
    VALUES (p_id_factura, p_id_descuento, v_monto)
    ON DUPLICATE KEY UPDATE monto_aplicado = v_monto;
  END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_cancelar_cita` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cancelar_cita`(IN p_id_cita INT UNSIGNED)
BEGIN
  DECLARE v_cliente INT UNSIGNED DEFAULT NULL;
  DECLARE v_estado  INT UNSIGNED DEFAULT NULL;
  DECLARE v_lock    INT UNSIGNED DEFAULT NULL;

  SELECT id_cita INTO v_lock FROM cita WHERE id_cita = p_id_cita FOR UPDATE;

  SELECT id_cliente, id_estado_cita INTO v_cliente, v_estado
    FROM cita WHERE id_cita = p_id_cita;

  IF v_estado IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cita no existe.';
  END IF;

  IF v_estado = 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Esa cita ya estaba cancelada.';
  END IF;

  
  IF v_estado = 4 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Esa cita ya fue atendida, asi que no se puede cancelar.';
  END IF;

  UPDATE cita SET id_estado_cita = 3 WHERE id_cita = p_id_cita;

  IF v_cliente IS NOT NULL THEN
    INSERT INTO notificacion (id_tipo_notificacion, id_cliente, id_cita, canal, mensaje, estado)
    VALUES (3, v_cliente, p_id_cita, 'WHATSAPP', 'Tu cita fue cancelada.', 'PENDIENTE');
  END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_canjear_servicio` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_canjear_servicio`(
    IN  p_id_cliente  INT UNSIGNED,
    IN  p_id_servicio INT UNSIGNED,
    OUT p_id_canje    INT UNSIGNED
)
BEGIN
  DECLARE v_lock    INT UNSIGNED DEFAULT NULL;
  DECLARE v_puntos  INT DEFAULT 0;
  DECLARE v_cuesta  INT DEFAULT 0;
  DECLARE v_dias    INT DEFAULT 0;
  DECLARE v_nombre  VARCHAR(100) DEFAULT '';

  SELECT id_cliente INTO v_lock FROM cliente WHERE id_cliente = p_id_cliente FOR UPDATE;
  IF v_lock IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ese cliente no existe.';
  END IF;

  SELECT sc.puntos, sc.dias_vigencia, s.nombre
    INTO v_cuesta, v_dias, v_nombre
  FROM servicio_canjeable sc
  JOIN servicio s ON s.id_servicio = sc.id_servicio
  WHERE sc.id_servicio = p_id_servicio AND sc.activo = 1 AND s.activo = 1;

  IF v_cuesta = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ese servicio no se puede canjear por puntos.';
  END IF;

  SET v_puntos = fn_cliente_puntos(p_id_cliente);
  IF v_puntos < v_cuesta THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No alcanzan los puntos para ese canje.';
  END IF;

  INSERT INTO canje (id_cliente, id_servicio, puntos, vence_en)
  VALUES (p_id_cliente, p_id_servicio, v_cuesta, DATE_ADD(CURDATE(), INTERVAL v_dias DAY));
  SET p_id_canje = LAST_INSERT_ID();

  
  
  CALL sp_registrar_puntos(p_id_cliente, NULL, 'CANJE', -v_cuesta,
       CONCAT('Canje por ', v_nombre));
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_cerrar_caja` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_cerrar_caja`(IN p_id_caja INT UNSIGNED)
BEGIN
  UPDATE caja
     SET id_estado_caja = 2,
         fecha_cierre   = NOW()
   WHERE id_caja = p_id_caja AND id_estado_caja = 1;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_confirmar_compra` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_confirmar_compra`(
  IN p_id_compra  INT UNSIGNED,
  IN p_id_usuario INT UNSIGNED
)
BEGIN
  DECLARE v_estado   INT UNSIGNED DEFAULT NULL;
  DECLARE v_sucursal INT UNSIGNED DEFAULT NULL;

  SELECT id_estado_compra, id_sucursal INTO v_estado, v_sucursal
  FROM compra WHERE id_compra = p_id_compra;

  IF v_estado IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La compra no existe.';
  END IF;

  IF v_estado = 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La compra ya fue confirmada.';
  END IF;

  INSERT INTO movimiento_inventario (id_producto, id_sucursal, id_usuario, id_tipo_movimiento,
                                     cantidad, precio_unitario, referencia, observaciones)
  SELECT dc.id_producto, v_sucursal, p_id_usuario, 1, dc.cantidad, dc.precio_unitario,
         CONCAT('COM#', p_id_compra), 'Entrada por compra confirmada'
  FROM detalle_compra dc WHERE dc.id_compra = p_id_compra;

  UPDATE producto p
    JOIN detalle_compra dc ON dc.id_producto = p.id_producto
     SET p.precio_costo = dc.precio_unitario
   WHERE dc.id_compra = p_id_compra;

  UPDATE compra SET id_estado_compra = 2 WHERE id_compra = p_id_compra;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_emitir_factura` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_emitir_factura`(
    IN  p_id_cliente           INT UNSIGNED,
    IN  p_id_cita              INT UNSIGNED,
    IN  p_id_usuario           INT UNSIGNED,
    IN  p_id_tipo_comprobante  INT UNSIGNED,
    IN  p_id_condicion_venta   INT UNSIGNED,
    OUT p_id_factura           INT UNSIGNED
)
BEGIN
  DECLARE v_timbrado  INT UNSIGNED DEFAULT NULL;
  DECLARE v_nro       INT UNSIGNED DEFAULT 0;
  DECLARE v_nivel     INT UNSIGNED DEFAULT NULL;
  DECLARE v_promo     INT UNSIGNED DEFAULT NULL;
  DECLARE v_m_nivel   DECIMAL(14,2) DEFAULT 0;
  DECLARE v_m_promo   DECIMAL(14,2) DEFAULT 0;

  SET v_timbrado = fn_timbrado_vigente(p_id_tipo_comprobante, CURRENT_DATE);
  IF v_timbrado IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No hay timbrado vigente para ese tipo de comprobante.';
  END IF;

  SET v_nro = fn_siguiente_correlativo(v_timbrado);

  INSERT INTO factura (id_cliente, id_cita, id_usuario, id_tipo_comprobante, id_condicion_venta,
                       id_timbrado, id_estado_factura, nro_correlativo)
  VALUES (p_id_cliente, p_id_cita, p_id_usuario, p_id_tipo_comprobante, p_id_condicion_venta,
          v_timbrado, 1, v_nro);
  SET p_id_factura = LAST_INSERT_ID();

  IF p_id_cita IS NOT NULL THEN
    INSERT INTO detalle_factura (id_factura, id_servicio, cantidad, precio_unitario, tasa_iva)
    SELECT p_id_factura, s.id_servicio, 1,
           CASE WHEN EXISTS (SELECT 1 FROM canje cj
                              WHERE cj.id_cita = p_id_cita
                                AND cj.id_servicio = s.id_servicio)
                THEN 0 ELSE s.precio END,
           s.tasa_iva
    FROM cita_servicio cs
    JOIN servicio s ON s.id_servicio = cs.id_servicio
    WHERE cs.id_cita = p_id_cita;

    UPDATE servicio_realizado sr
      JOIN detalle_factura df
        ON df.id_factura = p_id_factura AND df.id_servicio = sr.id_servicio
       SET sr.id_detalle_factura = df.id_detalle_factura
     WHERE sr.id_cita = p_id_cita AND sr.id_detalle_factura IS NULL;
  END IF;

  SET v_nivel = fn_cliente_descuento(p_id_cliente);
  SET v_promo = fn_promo_vigente(p_id_factura);
  SET v_m_nivel = fn_descuento_monto_factura(p_id_factura, v_nivel);
  SET v_m_promo = fn_descuento_monto_factura(p_id_factura, v_promo);

  IF v_m_promo > v_m_nivel THEN
    CALL sp_aplicar_descuento(p_id_factura, v_promo);
  ELSEIF v_m_nivel > 0 THEN
    CALL sp_aplicar_descuento(p_id_factura, v_nivel);
  END IF;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_emitir_nota_credito` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_emitir_nota_credito`(
    IN  p_id_factura_origen INT UNSIGNED,
    IN  p_id_usuario        INT UNSIGNED,
    IN  p_motivo            VARCHAR(300),
    OUT p_id_nota           INT UNSIGNED)
BEGIN
  DECLARE v_cliente   INT UNSIGNED DEFAULT NULL;
  DECLARE v_signo     TINYINT DEFAULT 0;
  DECLARE v_condicion INT UNSIGNED DEFAULT 1;
  DECLARE v_timbrado  INT UNSIGNED DEFAULT NULL;
  DECLARE v_nro       INT UNSIGNED DEFAULT 0;

  SELECT f.id_cliente, f.id_condicion_venta, tc.signo
    INTO v_cliente, v_condicion, v_signo
  FROM factura f
  JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
  WHERE f.id_factura = p_id_factura_origen;

  IF v_cliente IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La factura de origen no existe.';
  END IF;

  IF v_signo <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo se puede acreditar un comprobante de venta.';
  END IF;

  SET v_timbrado = fn_timbrado_vigente(5, CURRENT_DATE);
  IF v_timbrado IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No hay timbrado vigente para notas de credito.';
  END IF;

  SET v_nro = fn_siguiente_correlativo(v_timbrado);

  INSERT INTO factura (id_cliente, id_cita, id_usuario, id_tipo_comprobante, id_condicion_venta,
                       id_timbrado, id_estado_factura, id_factura_origen, nro_correlativo, observaciones)
  VALUES (v_cliente, NULL, p_id_usuario, 5, v_condicion,
          v_timbrado, 1, p_id_factura_origen, v_nro, p_motivo);
  SET p_id_nota = LAST_INSERT_ID();

  INSERT INTO detalle_factura (id_factura, id_servicio, id_producto, cantidad, precio_unitario, tasa_iva)
  SELECT p_id_nota, df.id_servicio, df.id_producto, df.cantidad, df.precio_unitario, df.tasa_iva
  FROM detalle_factura df
  WHERE df.id_factura = p_id_factura_origen;

  INSERT INTO factura_descuento (id_factura, id_descuento, monto_aplicado)
  SELECT p_id_nota, fd.id_descuento, fd.monto_aplicado
  FROM factura_descuento fd
  WHERE fd.id_factura = p_id_factura_origen;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_generar_recordatorios` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_generar_recordatorios`(IN p_horas INT)
BEGIN
  INSERT INTO notificacion (id_tipo_notificacion, id_cliente, id_cita, canal, mensaje, estado)
  SELECT 1, c.id_cliente, c.id_cita, 'WHATSAPP',
         CONCAT('Recordatorio: tu cita es el ', DATE_FORMAT(c.fecha_hora, '%d/%m/%Y a las %H:%i'), '.'),
         'PENDIENTE'
  FROM cita c
  JOIN estado_cita ec ON ec.id_estado_cita = c.id_estado_cita
  LEFT JOIN notificacion n ON n.id_cita = c.id_cita AND n.id_tipo_notificacion = 1
  WHERE ec.bloquea_agenda = 1
    AND c.fecha_hora BETWEEN NOW() AND (NOW() + INTERVAL p_horas HOUR)
    AND n.id_notificacion IS NULL;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_pagar_compra` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_pagar_compra`(
    IN  p_id_compra  INT UNSIGNED,
    IN  p_id_metodo  INT UNSIGNED,
    IN  p_id_usuario INT UNSIGNED,
    IN  p_monto      DECIMAL(14,2),
    IN  p_referencia VARCHAR(100),
    OUT p_id_pago    INT UNSIGNED)
BEGIN
  DECLARE v_estado    INT UNSIGNED DEFAULT NULL;
  DECLARE v_proveedor INT UNSIGNED DEFAULT NULL;
  DECLARE v_saldo     DECIMAL(14,2) DEFAULT 0;
  DECLARE v_caja      INT UNSIGNED DEFAULT NULL;

  SELECT id_estado_compra, id_proveedor INTO v_estado, v_proveedor
  FROM compra WHERE id_compra = p_id_compra;

  IF v_estado IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La compra no existe.';
  END IF;

  IF v_estado <> 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo se paga una compra confirmada.';
  END IF;

  SET v_saldo = fn_compra_saldo(p_id_compra);

  IF p_monto <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto tiene que ser mayor que cero.';
  END IF;

  IF p_monto > v_saldo THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto supera el saldo pendiente de la compra.';
  END IF;

  SELECT id_caja INTO v_caja FROM caja
   WHERE id_usuario = p_id_usuario AND id_estado_caja = 1
   ORDER BY id_caja DESC LIMIT 1;

  INSERT INTO pago_proveedor (id_proveedor, id_usuario, id_metodo_pago, id_estado_pago_proveedor, id_caja, referencia)
  VALUES (v_proveedor, p_id_usuario, p_id_metodo, 1, v_caja, p_referencia);
  SET p_id_pago = LAST_INSERT_ID();

  INSERT INTO detalle_pago_proveedor (id_pago_proveedor, id_compra, monto_aplicado)
  VALUES (p_id_pago, p_id_compra, p_monto);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_registrar_cobro` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_cobro`(
    IN  p_id_factura  INT UNSIGNED,
    IN  p_id_metodo   INT UNSIGNED,
    IN  p_id_usuario  INT UNSIGNED,
    IN  p_monto       DECIMAL(14,2),
    IN  p_referencia  VARCHAR(100),
    OUT p_id_cobro    INT UNSIGNED
)
BEGIN
  DECLARE v_estado INT UNSIGNED DEFAULT NULL;
  DECLARE v_signo  TINYINT DEFAULT 0;
  DECLARE v_saldo  DECIMAL(14,2) DEFAULT 0;
  DECLARE v_caja   INT UNSIGNED DEFAULT NULL;
  DECLARE v_lock   INT UNSIGNED DEFAULT NULL;

  
  
  SELECT id_factura INTO v_lock FROM factura WHERE id_factura = p_id_factura FOR UPDATE;

  SELECT f.id_estado_factura, tc.signo
    INTO v_estado, v_signo
  FROM factura f
  JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = f.id_tipo_comprobante
  WHERE f.id_factura = p_id_factura;

  IF v_estado IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La factura no existe.';
  END IF;

  IF v_estado = 2 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La factura esta anulada.';
  END IF;

  IF v_signo <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ese tipo de comprobante no se cobra.';
  END IF;

  SET v_saldo = fn_factura_saldo(p_id_factura);
  IF p_monto > v_saldo THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El monto supera el saldo pendiente de la factura.';
  END IF;

  SELECT id_caja INTO v_caja FROM caja
   WHERE id_usuario = p_id_usuario AND id_estado_caja = 1
   ORDER BY id_caja DESC LIMIT 1;

  INSERT INTO cobro (id_factura, id_metodo_pago, id_estado_cobro, id_usuario, id_caja, monto, referencia)
  VALUES (p_id_factura, p_id_metodo, 1, p_id_usuario, v_caja, p_monto, p_referencia);
  SET p_id_cobro = LAST_INSERT_ID();
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_registrar_movimiento_inventario` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_movimiento_inventario`(
  IN p_id_producto        INT UNSIGNED,
  IN p_id_sucursal        INT UNSIGNED,
  IN p_id_usuario         INT UNSIGNED,
  IN p_id_tipo_movimiento INT UNSIGNED,
  IN p_cantidad           DECIMAL(12,4),
  IN p_precio_unitario    DECIMAL(12,2),
  IN p_referencia         VARCHAR(50),
  IN p_observaciones      VARCHAR(255)
)
BEGIN
  INSERT INTO movimiento_inventario (id_producto, id_sucursal, id_usuario, id_tipo_movimiento,
                                     cantidad, precio_unitario, referencia, observaciones)
  VALUES (p_id_producto, p_id_sucursal, p_id_usuario, p_id_tipo_movimiento,
          p_cantidad, p_precio_unitario, p_referencia, p_observaciones);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_registrar_pago_personal` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_pago_personal`(
    IN  p_id_usuario          INT UNSIGNED,
    IN  p_id_usuario_registro INT UNSIGNED,
    IN  p_periodo             VARCHAR(40),
    IN  p_id_metodo_pago      INT UNSIGNED,
    IN  p_id_caja             INT UNSIGNED,
    OUT p_id_pago             INT UNSIGNED
)
BEGIN
  INSERT INTO pago_personal (id_usuario, id_usuario_registro, id_metodo_pago, id_estado_pago, id_caja, periodo)
  VALUES (p_id_usuario, p_id_usuario_registro, p_id_metodo_pago, 1, p_id_caja, p_periodo);
  SET p_id_pago = LAST_INSERT_ID();

  INSERT INTO detalle_pago_personal (id_pago_personal, id_servicio_realizado, monto)
  SELECT p_id_pago, sr.id_servicio_realizado, fn_comision_servicio(sr.id_servicio_realizado)
  FROM servicio_realizado sr
  LEFT JOIN detalle_pago_personal d ON d.id_servicio_realizado = sr.id_servicio_realizado
  WHERE sr.id_usuario = p_id_usuario
    AND d.id_detalle_pago IS NULL;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_registrar_puntos` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_puntos`(
    IN p_id_cliente    INT UNSIGNED,
    IN p_id_factura    INT UNSIGNED,
    IN p_tipo          VARCHAR(10),
    IN p_puntos        INT,
    IN p_observaciones VARCHAR(300))
BEGIN
  INSERT INTO movimiento_punto (id_cliente, id_factura, tipo, puntos, observaciones)
  VALUES (p_id_cliente, p_id_factura, p_tipo, p_puntos, p_observaciones);
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_registrar_sena` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_registrar_sena`(
    IN  p_id_cita    INT UNSIGNED,
    IN  p_id_metodo  INT UNSIGNED,
    IN  p_id_usuario INT UNSIGNED,
    IN  p_monto      DECIMAL(14,2),
    IN  p_referencia VARCHAR(100),
    OUT p_id_cobro   INT UNSIGNED
)
BEGIN
  DECLARE v_estado INT UNSIGNED DEFAULT NULL;
  DECLARE v_caja   INT UNSIGNED DEFAULT NULL;
  DECLARE v_total  DECIMAL(14,2) DEFAULT 0;
  DECLARE v_senado DECIMAL(14,2) DEFAULT 0;
  DECLARE v_lock   INT UNSIGNED DEFAULT NULL;

  
  
  SELECT id_cita INTO v_lock FROM cita WHERE id_cita = p_id_cita FOR UPDATE;

  SELECT id_estado_cita INTO v_estado FROM cita WHERE id_cita = p_id_cita;

  IF v_estado IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cita no existe.';
  END IF;

  IF p_monto <= 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La sena tiene que ser mayor que cero.';
  END IF;

  
  SELECT COALESCE(SUM(s.precio), 0) INTO v_total
    FROM cita_servicio cs
    JOIN servicio s ON s.id_servicio = cs.id_servicio
   WHERE cs.id_cita = p_id_cita;

  IF v_total <= 0 THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'Esa cita no tiene servicios cargados, asi que no hay monto que cobrar.';
  END IF;

  SET v_senado = fn_cita_sena(p_id_cita);

  IF v_senado + p_monto > v_total THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'El monto no puede superar lo que valen los servicios de la cita.';
  END IF;

  SELECT id_caja INTO v_caja FROM caja
   WHERE id_usuario = p_id_usuario AND id_estado_caja = 1
   ORDER BY id_caja DESC LIMIT 1;

  INSERT INTO cobro (id_factura, id_cita, id_metodo_pago, id_estado_cobro, id_usuario, id_caja, monto, referencia, observaciones)
  VALUES (NULL, p_id_cita, p_id_metodo, 1, p_id_usuario, v_caja, p_monto, p_referencia, 'Sena de reserva');
  SET p_id_cobro = LAST_INSERT_ID();
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_reprogramar_cita` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_reprogramar_cita`(IN p_id_cita INT UNSIGNED, IN p_nueva_fecha DATETIME)
BEGIN
  DECLARE v_usuario INT UNSIGNED DEFAULT NULL;
  DECLARE v_estado  INT UNSIGNED DEFAULT NULL;
  DECLARE v_lock    INT UNSIGNED;

  
  
  SELECT id_cita INTO v_lock FROM cita WHERE id_cita = p_id_cita FOR UPDATE;

  SELECT id_usuario, id_estado_cita INTO v_usuario, v_estado
    FROM cita WHERE id_cita = p_id_cita;

  IF v_usuario IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La cita no existe.';
  END IF;

  
  
  IF v_estado = 3 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Esa cita fue cancelada, asi que no se puede reprogramar.';
  END IF;

  IF v_estado = 4 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Esa cita ya fue atendida, asi que no se puede reprogramar.';
  END IF;

  
  SELECT id_usuario INTO v_lock FROM usuario
   WHERE id_usuario = v_usuario FOR UPDATE;

  IF fn_verificar_disponibilidad(v_usuario, p_nueva_fecha, fn_cita_duracion(p_id_cita), p_id_cita) = 0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El profesional no esta disponible en el nuevo horario.';
  END IF;

  UPDATE cita SET fecha_hora = p_nueva_fecha, id_estado_cita = 2 WHERE id_cita = p_id_cita;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
/*!50003 DROP PROCEDURE IF EXISTS `sp_revertir_pago_personal` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_revertir_pago_personal`(
    IN p_id_pago    INT UNSIGNED,
    IN p_id_usuario INT UNSIGNED)
BEGIN
  SET @usuario_actual = p_id_usuario;
  UPDATE pago_personal SET id_estado_pago = 4 WHERE id_pago_personal = p_id_pago;
  DELETE FROM detalle_pago_personal WHERE id_pago_personal = p_id_pago;
  SET @usuario_actual = NULL;
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Final view structure for view `vw_agenda_bloqueos`
--

/*!50001 DROP VIEW IF EXISTS `vw_agenda_bloqueos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_agenda_bloqueos` AS select `a`.`id_ausencia` AS `id_ausencia`,coalesce(trim(concat_ws(' ',`pu`.`nombre`,`pu`.`apellido`)),'Todo el salon') AS `alcance`,`ta`.`nombre` AS `tipo`,`a`.`fecha_inicio` AS `fecha_inicio`,`a`.`fecha_fin` AS `fecha_fin`,`a`.`motivo` AS `motivo` from (((`ausencia_agenda` `a` join `tipo_ausencia` `ta` on(`ta`.`id_tipo_ausencia` = `a`.`id_tipo_ausencia`)) left join `usuario` `u` on(`u`.`id_usuario` = `a`.`id_usuario`)) left join `persona` `pu` on(`pu`.`id_persona` = `u`.`id_persona`)) where `a`.`activo` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_agenda_citas`
--

/*!50001 DROP VIEW IF EXISTS `vw_agenda_citas`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_agenda_citas` AS select `c`.`id_cita` AS `id_cita`,`c`.`fecha_hora` AS `fecha_hora`,`fn_cita_duracion`(`c`.`id_cita`) AS `duracion_min`,trim(concat_ws(' ',`pc`.`nombre`,`pc`.`apellido`)) AS `cliente`,`pc`.`telefono` AS `telefono`,trim(concat_ws(' ',`pu`.`nombre`,`pu`.`apellido`)) AS `profesional`,`ec`.`nombre` AS `estado`,(select group_concat(`s`.`nombre` order by `s`.`nombre` ASC separator ', ') from (`cita_servicio` `cs` join `servicio` `s` on(`s`.`id_servicio` = `cs`.`id_servicio`)) where `cs`.`id_cita` = `c`.`id_cita`) AS `servicios`,`c`.`observaciones` AS `observaciones` from (((((`cita` `c` join `cliente` `cl` on(`cl`.`id_cliente` = `c`.`id_cliente`)) join `persona` `pc` on(`pc`.`id_persona` = `cl`.`id_persona`)) join `usuario` `u` on(`u`.`id_usuario` = `c`.`id_usuario`)) join `persona` `pu` on(`pu`.`id_persona` = `u`.`id_persona`)) join `estado_cita` `ec` on(`ec`.`id_estado_cita` = `c`.`id_estado_cita`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_caja_resumen`
--

/*!50001 DROP VIEW IF EXISTS `vw_caja_resumen`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = cp850 */;
/*!50001 SET character_set_results     = cp850 */;
/*!50001 SET collation_connection      = cp850_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_caja_resumen` AS select `ca`.`id_sucursal` AS `id_sucursal`,`ca`.`id_caja` AS `id_caja`,trim(concat_ws(' ',`pu`.`nombre`,`pu`.`apellido`)) AS `responsable`,`ec`.`nombre` AS `estado`,`ca`.`fecha_apertura` AS `fecha_apertura`,`ca`.`fecha_cierre` AS `fecha_cierre`,`ca`.`monto_inicial` AS `monto_inicial`,(select coalesce(sum(`co`.`monto`),0) from (`cobro` `co` join `metodo_pago` `mp` on(`mp`.`id_metodo_pago` = `co`.`id_metodo_pago`)) where `co`.`id_caja` = `ca`.`id_caja` and `co`.`id_estado_cobro` = 1 and `mp`.`tipo` = 'EFECTIVO') AS `cobros_efectivo`,(select coalesce(sum(`co`.`monto`),0) from (`cobro` `co` join `metodo_pago` `mp` on(`mp`.`id_metodo_pago` = `co`.`id_metodo_pago`)) where `co`.`id_caja` = `ca`.`id_caja` and `co`.`id_estado_cobro` = 1 and `mp`.`tipo` <> 'EFECTIVO') AS `cobros_otros`,(select coalesce(sum(`co`.`monto`),0) from `cobro` `co` where `co`.`id_caja` = `ca`.`id_caja` and `co`.`id_estado_cobro` = 1) AS `cobros`,(select coalesce(sum(`mc`.`monto`),0) from `movimiento_caja` `mc` where `mc`.`id_caja` = `ca`.`id_caja` and `mc`.`tipo` = 'INGRESO') AS `otros_ingresos`,(select coalesce(sum(`mc`.`monto`),0) from `movimiento_caja` `mc` where `mc`.`id_caja` = `ca`.`id_caja` and `mc`.`tipo` = 'EGRESO') AS `egresos`,(select coalesce(sum(`fn_pago_proveedor_monto`(`pp`.`id_pago_proveedor`)),0) from (`pago_proveedor` `pp` join `metodo_pago` `mp` on(`mp`.`id_metodo_pago` = `pp`.`id_metodo_pago`)) where `pp`.`id_caja` = `ca`.`id_caja` and `pp`.`id_estado_pago_proveedor` = 1 and `mp`.`tipo` = 'EFECTIVO') AS `pagos_prov_efectivo`,(select coalesce(sum(`fn_pago_proveedor_monto`(`pp`.`id_pago_proveedor`)),0) from (`pago_proveedor` `pp` join `metodo_pago` `mp` on(`mp`.`id_metodo_pago` = `pp`.`id_metodo_pago`)) where `pp`.`id_caja` = `ca`.`id_caja` and `pp`.`id_estado_pago_proveedor` = 1 and `mp`.`tipo` <> 'EFECTIVO') AS `pagos_prov_otros`,(select coalesce(sum(`fn_pago_proveedor_monto`(`pp`.`id_pago_proveedor`)),0) from `pago_proveedor` `pp` where `pp`.`id_caja` = `ca`.`id_caja` and `pp`.`id_estado_pago_proveedor` = 1) AS `pagos_proveedor`,(select coalesce(sum(`fn_pago_personal_monto`(`pg`.`id_pago_personal`)),0) from (`pago_personal` `pg` join `metodo_pago` `mp` on(`mp`.`id_metodo_pago` = `pg`.`id_metodo_pago`)) where `pg`.`id_caja` = `ca`.`id_caja` and `pg`.`id_estado_pago` = 1 and `mp`.`tipo` = 'EFECTIVO') AS `pagos_pers_efectivo`,(select coalesce(sum(`fn_pago_personal_monto`(`pg`.`id_pago_personal`)),0) from (`pago_personal` `pg` join `metodo_pago` `mp` on(`mp`.`id_metodo_pago` = `pg`.`id_metodo_pago`)) where `pg`.`id_caja` = `ca`.`id_caja` and `pg`.`id_estado_pago` = 1 and `mp`.`tipo` <> 'EFECTIVO') AS `pagos_pers_otros`,(select coalesce(sum(`fn_pago_personal_monto`(`pg`.`id_pago_personal`)),0) from `pago_personal` `pg` where `pg`.`id_caja` = `ca`.`id_caja` and `pg`.`id_estado_pago` = 1) AS `pagos_personal`,`fn_caja_saldo`(`ca`.`id_caja`) AS `saldo` from (((`caja` `ca` join `usuario` `u` on(`u`.`id_usuario` = `ca`.`id_usuario`)) join `persona` `pu` on(`pu`.`id_persona` = `u`.`id_persona`)) join `estado_caja` `ec` on(`ec`.`id_estado_caja` = `ca`.`id_estado_caja`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_cliente_fidelizacion`
--

/*!50001 DROP VIEW IF EXISTS `vw_cliente_fidelizacion`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_cliente_fidelizacion` AS select `cl`.`id_cliente` AS `id_cliente`,trim(concat_ws(' ',`pc`.`nombre`,`pc`.`apellido`)) AS `cliente`,`pc`.`telefono` AS `telefono`,`fn_cliente_visitas`(`cl`.`id_cliente`) AS `visitas`,`fn_cliente_puntos`(`cl`.`id_cliente`) AS `puntos`,`n`.`nombre` AS `nivel`,`d`.`nombre` AS `descuento_del_nivel` from (((`cliente` `cl` join `persona` `pc` on(`pc`.`id_persona` = `cl`.`id_persona`)) left join `nivel` `n` on(`n`.`id_nivel` = `fn_cliente_nivel`(`cl`.`id_cliente`))) left join `descuento` `d` on(`d`.`id_descuento` = `n`.`id_descuento`)) where `cl`.`activo` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_compra_resumen`
--

/*!50001 DROP VIEW IF EXISTS `vw_compra_resumen`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_compra_resumen` AS select `c`.`id_compra` AS `id_compra`,`c`.`fecha` AS `fecha`,`pp`.`nombre` AS `proveedor`,trim(concat_ws(' ',`pu`.`nombre`,`pu`.`apellido`)) AS `registro`,`ec`.`nombre` AS `estado`,`fn_compra_total`(`c`.`id_compra`) AS `total`,`c`.`observaciones` AS `observaciones` from (((((`compra` `c` join `proveedor` `pr` on(`pr`.`id_proveedor` = `c`.`id_proveedor`)) join `persona` `pp` on(`pp`.`id_persona` = `pr`.`id_persona`)) join `usuario` `u` on(`u`.`id_usuario` = `c`.`id_usuario`)) join `persona` `pu` on(`pu`.`id_persona` = `u`.`id_persona`)) join `estado_compra` `ec` on(`ec`.`id_estado_compra` = `c`.`id_estado_compra`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_cuenta_proveedor`
--

/*!50001 DROP VIEW IF EXISTS `vw_cuenta_proveedor`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_cuenta_proveedor` AS select `c`.`id_compra` AS `id_compra`,`p`.`id_proveedor` AS `id_proveedor`,`pp`.`nombre` AS `proveedor`,`c`.`fecha` AS `fecha`,`c`.`nro_factura_proveedor` AS `nro_factura_proveedor`,`cv`.`nombre` AS `condicion`,`fn_compra_vencimiento`(`c`.`id_compra`) AS `vencimiento`,`fn_compra_total`(`c`.`id_compra`) AS `total`,`fn_compra_total`(`c`.`id_compra`) - `fn_compra_saldo`(`c`.`id_compra`) AS `pagado`,`fn_compra_saldo`(`c`.`id_compra`) AS `saldo`,if(`fn_compra_saldo`(`c`.`id_compra`) > 0 and `fn_compra_vencimiento`(`c`.`id_compra`) < curdate(),1,0) AS `vencida` from (((`compra` `c` join `proveedor` `p` on(`p`.`id_proveedor` = `c`.`id_proveedor`)) join `persona` `pp` on(`pp`.`id_persona` = `p`.`id_persona`)) join `condicion_venta` `cv` on(`cv`.`id_condicion_venta` = `c`.`id_condicion_venta`)) where `c`.`id_estado_compra` = 2 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_demanda_por_hora`
--

/*!50001 DROP VIEW IF EXISTS `vw_demanda_por_hora`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_demanda_por_hora` AS select hour(`c`.`fecha_hora`) AS `hora`,count(0) AS `citas`,sum(case when `c`.`id_estado_cita` = 4 then 1 else 0 end) AS `atendidas`,sum(case when `c`.`id_estado_cita` = 6 then 1 else 0 end) AS `ausencias`,sum(case when `c`.`id_estado_cita` = 3 then 1 else 0 end) AS `canceladas` from `cita` `c` group by hour(`c`.`fecha_hora`) order by hour(`c`.`fecha_hora`) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_detalle_factura`
--

/*!50001 DROP VIEW IF EXISTS `vw_detalle_factura`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_detalle_factura` AS select `df`.`id_detalle_factura` AS `id_detalle_factura`,`df`.`id_factura` AS `id_factura`,coalesce(`s`.`nombre`,`p`.`nombre`) AS `item`,if(`df`.`id_servicio` is not null,'Servicio','Producto') AS `clase`,`df`.`cantidad` AS `cantidad`,`df`.`precio_unitario` AS `precio_unitario`,`df`.`tasa_iva` AS `tasa_iva`,round(`df`.`cantidad` * `df`.`precio_unitario`,2) AS `subtotal` from ((`detalle_factura` `df` left join `servicio` `s` on(`s`.`id_servicio` = `df`.`id_servicio`)) left join `producto` `p` on(`p`.`id_producto` = `df`.`id_producto`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_factura_impuestos`
--

/*!50001 DROP VIEW IF EXISTS `vw_factura_impuestos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_factura_impuestos` AS select `f`.`id_factura` AS `id_factura`,`fn_factura_nro`(`f`.`id_factura`) AS `nro_comprobante`,`tc`.`nombre` AS `tipo_comprobante`,`tc`.`signo` AS `signo`,round(sum(case when `df`.`tasa_iva` = 10 then round(`df`.`cantidad` * `df`.`precio_unitario`,2) else 0 end) * if(sum(round(`df`.`cantidad` * `df`.`precio_unitario`,2)) > 0,(sum(round(`df`.`cantidad` * `df`.`precio_unitario`,2)) - `fn_factura_descuento`(`f`.`id_factura`)) / sum(round(`df`.`cantidad` * `df`.`precio_unitario`,2)),1),2) AS `gravado_10`,round(sum(case when `df`.`tasa_iva` = 10 then round(`df`.`cantidad` * `df`.`precio_unitario`,2) else 0 end) * if(sum(round(`df`.`cantidad` * `df`.`precio_unitario`,2)) > 0,(sum(round(`df`.`cantidad` * `df`.`precio_unitario`,2)) - `fn_factura_descuento`(`f`.`id_factura`)) / sum(round(`df`.`cantidad` * `df`.`precio_unitario`,2)),1) / 11,2) AS `iva_10`,round(sum(case when `df`.`tasa_iva` = 5 then round(`df`.`cantidad` * `df`.`precio_unitario`,2) else 0 end) * if(sum(round(`df`.`cantidad` * `df`.`precio_unitario`,2)) > 0,(sum(round(`df`.`cantidad` * `df`.`precio_unitario`,2)) - `fn_factura_descuento`(`f`.`id_factura`)) / sum(round(`df`.`cantidad` * `df`.`precio_unitario`,2)),1),2) AS `gravado_5`,round(sum(case when `df`.`tasa_iva` = 5 then round(`df`.`cantidad` * `df`.`precio_unitario`,2) else 0 end) * if(sum(round(`df`.`cantidad` * `df`.`precio_unitario`,2)) > 0,(sum(round(`df`.`cantidad` * `df`.`precio_unitario`,2)) - `fn_factura_descuento`(`f`.`id_factura`)) / sum(round(`df`.`cantidad` * `df`.`precio_unitario`,2)),1) / 21,2) AS `iva_5`,round(sum(case when `df`.`tasa_iva` = 0 then round(`df`.`cantidad` * `df`.`precio_unitario`,2) else 0 end) * if(sum(round(`df`.`cantidad` * `df`.`precio_unitario`,2)) > 0,(sum(round(`df`.`cantidad` * `df`.`precio_unitario`,2)) - `fn_factura_descuento`(`f`.`id_factura`)) / sum(round(`df`.`cantidad` * `df`.`precio_unitario`,2)),1),2) AS `exentas`,`fn_factura_total`(`f`.`id_factura`) AS `total_comprobante` from ((`factura` `f` join `tipo_comprobante` `tc` on(`tc`.`id_tipo_comprobante` = `f`.`id_tipo_comprobante`)) join `detalle_factura` `df` on(`df`.`id_factura` = `f`.`id_factura`)) group by `f`.`id_factura`,`tc`.`nombre`,`tc`.`signo` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_factura_resumen`
--

/*!50001 DROP VIEW IF EXISTS `vw_factura_resumen`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_factura_resumen` AS select `f`.`id_factura` AS `id_factura`,`f`.`fecha_emision` AS `fecha_emision`,`fn_factura_nro`(`f`.`id_factura`) AS `nro_comprobante`,`tc`.`nombre` AS `tipo_comprobante`,`tc`.`signo` AS `signo`,trim(concat_ws(' ',`pc`.`nombre`,`pc`.`apellido`)) AS `cliente`,`cv`.`nombre` AS `condicion_venta`,`fn_factura_vencimiento`(`f`.`id_factura`) AS `fecha_vencimiento`,`ef`.`nombre` AS `estado`,`fn_factura_subtotal`(`f`.`id_factura`) AS `subtotal`,`fn_factura_descuento`(`f`.`id_factura`) AS `descuento_total`,`fn_factura_total`(`f`.`id_factura`) AS `total`,`fn_factura_total`(`f`.`id_factura`) * `tc`.`signo` AS `total_neto`,`fn_factura_total`(`f`.`id_factura`) - `fn_factura_saldo`(`f`.`id_factura`) AS `cobrado`,`fn_factura_saldo`(`f`.`id_factura`) AS `saldo`,`fn_factura_nro`(`f`.`id_factura_origen`) AS `comprobante_origen` from (((((`factura` `f` join `tipo_comprobante` `tc` on(`tc`.`id_tipo_comprobante` = `f`.`id_tipo_comprobante`)) join `condicion_venta` `cv` on(`cv`.`id_condicion_venta` = `f`.`id_condicion_venta`)) join `estado_factura` `ef` on(`ef`.`id_estado_factura` = `f`.`id_estado_factura`)) join `cliente` `cl` on(`cl`.`id_cliente` = `f`.`id_cliente`)) join `persona` `pc` on(`pc`.`id_persona` = `cl`.`id_persona`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_habilitacion_profesional`
--

/*!50001 DROP VIEW IF EXISTS `vw_habilitacion_profesional`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_habilitacion_profesional` AS select trim(concat_ws(' ',`pu`.`nombre`,`pu`.`apellido`)) AS `profesional`,`s`.`nombre` AS `servicio`,`cs`.`nombre` AS `categoria`,coalesce(`us`.`duracion_min`,`s`.`duracion_min`) AS `duracion_min`,`s`.`precio` AS `precio`,(select concat(`c`.`tipo`,' ',`c`.`valor`) from `comision` `c` where `c`.`id_usuario` = `u`.`id_usuario` and (`c`.`id_servicio` = `s`.`id_servicio` or `c`.`id_servicio` is null) and `c`.`activo` = 1 and `c`.`vigente_desde` <= curdate() order by `c`.`id_servicio` is null,`c`.`vigente_desde` desc limit 1) AS `comision_vigente` from ((((`usuario_servicio` `us` join `usuario` `u` on(`u`.`id_usuario` = `us`.`id_usuario`)) join `persona` `pu` on(`pu`.`id_persona` = `u`.`id_persona`)) join `servicio` `s` on(`s`.`id_servicio` = `us`.`id_servicio`)) join `categoria_servicio` `cs` on(`cs`.`id_categoria_servicio` = `s`.`id_categoria_servicio`)) where `us`.`activo` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_historial_cliente`
--

/*!50001 DROP VIEW IF EXISTS `vw_historial_cliente`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_historial_cliente` AS select `cl`.`id_cliente` AS `id_cliente`,trim(concat_ws(' ',`pc`.`nombre`,`pc`.`apellido`)) AS `cliente`,`c`.`id_cita` AS `id_cita`,`sr`.`fecha_hora` AS `fecha_hora`,`s`.`nombre` AS `servicio`,`s`.`precio` AS `precio`,trim(concat_ws(' ',`pu`.`nombre`,`pu`.`apellido`)) AS `profesional`,`fn_factura_nro`(`df`.`id_factura`) AS `nro_comprobante`,`cal`.`puntaje` AS `puntaje` from ((((((((`servicio_realizado` `sr` join `cita` `c` on(`c`.`id_cita` = `sr`.`id_cita`)) join `cliente` `cl` on(`cl`.`id_cliente` = `c`.`id_cliente`)) join `persona` `pc` on(`pc`.`id_persona` = `cl`.`id_persona`)) join `servicio` `s` on(`s`.`id_servicio` = `sr`.`id_servicio`)) join `usuario` `u` on(`u`.`id_usuario` = `sr`.`id_usuario`)) join `persona` `pu` on(`pu`.`id_persona` = `u`.`id_persona`)) left join `detalle_factura` `df` on(`df`.`id_detalle_factura` = `sr`.`id_detalle_factura`)) left join `calificacion` `cal` on(`cal`.`id_cita` = `c`.`id_cita`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_pago_personal_resumen`
--

/*!50001 DROP VIEW IF EXISTS `vw_pago_personal_resumen`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_pago_personal_resumen` AS select `pp`.`id_pago_personal` AS `id_pago_personal`,`pp`.`fecha` AS `fecha`,`pp`.`periodo` AS `periodo`,trim(concat_ws(' ',`pu`.`nombre`,`pu`.`apellido`)) AS `beneficiario`,`ep`.`nombre` AS `estado`,(select count(0) from `detalle_pago_personal` `d` where `d`.`id_pago_personal` = `pp`.`id_pago_personal`) AS `servicios`,`fn_pago_personal_monto`(`pp`.`id_pago_personal`) AS `monto` from (((`pago_personal` `pp` join `usuario` `u` on(`u`.`id_usuario` = `pp`.`id_usuario`)) join `persona` `pu` on(`pu`.`id_persona` = `u`.`id_persona`)) join `estado_pago_personal` `ep` on(`ep`.`id_estado_pago` = `pp`.`id_estado_pago`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_producto_bajo_stock`
--

/*!50001 DROP VIEW IF EXISTS `vw_producto_bajo_stock`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_producto_bajo_stock` AS select `v`.`id_producto` AS `id_producto`,`v`.`id_sucursal` AS `id_sucursal`,`v`.`nombre` AS `nombre`,`v`.`categoria` AS `categoria`,`v`.`stock_actual` AS `stock_actual`,`v`.`stock_minimo` AS `stock_minimo`,`v`.`stock_minimo` - `v`.`stock_actual` AS `faltante`,`v`.`precio_costo` AS `precio_costo` from `vw_producto_stock` `v` where `v`.`activo` = 1 and `v`.`stock_actual` <= `v`.`stock_minimo` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_producto_stock`
--

/*!50001 DROP VIEW IF EXISTS `vw_producto_stock`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_producto_stock` AS select `p`.`id_producto` AS `id_producto`,`ps`.`id_sucursal` AS `id_sucursal`,`p`.`nombre` AS `nombre`,`cp`.`nombre` AS `categoria`,`p`.`unidad_medida` AS `unidad_medida`,`fn_producto_stock`(`p`.`id_producto`,`ps`.`id_sucursal`) AS `stock_actual`,`ps`.`stock_minimo` AS `stock_minimo`,`p`.`precio_costo` AS `precio_costo`,`p`.`precio_venta` AS `precio_venta`,`p`.`activo` = 1 and `ps`.`activo` = 1 AS `activo` from ((`producto` `p` join `producto_sucursal` `ps` on(`ps`.`id_producto` = `p`.`id_producto`)) join `categoria_producto` `cp` on(`cp`.`id_categoria` = `p`.`id_categoria`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_servicios_mas_solicitados`
--

/*!50001 DROP VIEW IF EXISTS `vw_servicios_mas_solicitados`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_servicios_mas_solicitados` AS select `s`.`id_servicio` AS `id_servicio`,`s`.`nombre` AS `servicio`,`cs`.`nombre` AS `categoria`,count(`sr`.`id_servicio_realizado`) AS `veces_realizado`,coalesce(sum(case when `sr`.`id_servicio_realizado` is not null then `s`.`precio` else 0 end),0) AS `ingreso_generado` from ((`servicio` `s` join `categoria_servicio` `cs` on(`cs`.`id_categoria_servicio` = `s`.`id_categoria_servicio`)) left join `servicio_realizado` `sr` on(`sr`.`id_servicio` = `s`.`id_servicio`)) group by `s`.`id_servicio`,`s`.`nombre`,`cs`.`nombre` order by count(`sr`.`id_servicio_realizado`) desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_servicios_por_profesional`
--

/*!50001 DROP VIEW IF EXISTS `vw_servicios_por_profesional`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_servicios_por_profesional` AS select `sr`.`id_servicio_realizado` AS `id_servicio_realizado`,`sr`.`fecha_hora` AS `fecha_hora`,trim(concat_ws(' ',`pu`.`nombre`,`pu`.`apellido`)) AS `profesional`,`s`.`nombre` AS `servicio`,`s`.`precio` AS `precio`,if(`dpp`.`id_detalle_pago` is null,0,1) AS `pagado` from ((((`servicio_realizado` `sr` join `usuario` `u` on(`u`.`id_usuario` = `sr`.`id_usuario`)) join `persona` `pu` on(`pu`.`id_persona` = `u`.`id_persona`)) join `servicio` `s` on(`s`.`id_servicio` = `sr`.`id_servicio`)) left join `detalle_pago_personal` `dpp` on(`dpp`.`id_servicio_realizado` = `sr`.`id_servicio_realizado`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-17  9:53:23
