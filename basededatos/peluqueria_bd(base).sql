-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: peluqueria_bd
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `asistencia`
--

LOCK TABLES `asistencia` WRITE;
/*!40000 ALTER TABLE `asistencia` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditoria`
--

LOCK TABLES `auditoria` WRITE;
/*!40000 ALTER TABLE `auditoria` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ausencia_agenda`
--

LOCK TABLES `ausencia_agenda` WRITE;
/*!40000 ALTER TABLE `ausencia_agenda` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `caja`
--

LOCK TABLES `caja` WRITE;
/*!40000 ALTER TABLE `caja` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `calificacion`
--

LOCK TABLES `calificacion` WRITE;
/*!40000 ALTER TABLE `calificacion` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cita`
--

LOCK TABLES `cita` WRITE;
/*!40000 ALTER TABLE `cita` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cita_servicio`
--

LOCK TABLES `cita_servicio` WRITE;
/*!40000 ALTER TABLE `cita_servicio` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cliente`
--

LOCK TABLES `cliente` WRITE;
/*!40000 ALTER TABLE `cliente` DISABLE KEYS */;
INSERT INTO `cliente` VALUES (1,2,'2026-07-14 19:42:29',NULL,1,2);
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cobro`
--

LOCK TABLES `cobro` WRITE;
/*!40000 ALTER TABLE `cobro` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cobro_banco`
--

LOCK TABLES `cobro_banco` WRITE;
/*!40000 ALTER TABLE `cobro_banco` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cobro_tarjeta`
--

LOCK TABLES `cobro_tarjeta` WRITE;
/*!40000 ALTER TABLE `cobro_tarjeta` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comision`
--

LOCK TABLES `comision` WRITE;
/*!40000 ALTER TABLE `comision` DISABLE KEYS */;
INSERT INTO `comision` VALUES (8,12,NULL,'PORCENTAJE',15.00,'2026-08-14',1),(9,10,NULL,'PORCENTAJE',15.00,'2026-08-14',1),(10,11,NULL,'PORCENTAJE',15.00,'2026-08-14',1),(11,13,NULL,'PORCENTAJE',15.00,'2026-08-14',1);
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compra`
--

LOCK TABLES `compra` WRITE;
/*!40000 ALTER TABLE `compra` DISABLE KEYS */;
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
  `puntos_cada_gs` int(10) unsigned NOT NULL DEFAULT 10000,
  PRIMARY KEY (`id_configuracion`),
  CONSTRAINT `chk_config_unica` CHECK (`id_configuracion` = 1),
  CONSTRAINT `chk_config_puntos` CHECK (`puntos_cada_gs` between 100 and 10000000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracion`
--

LOCK TABLES `configuracion` WRITE;
/*!40000 ALTER TABLE `configuracion` DISABLE KEYS */;
INSERT INTO `configuracion` VALUES (1,10000);
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `contacto_soporte`
--

LOCK TABLES `contacto_soporte` WRITE;
/*!40000 ALTER TABLE `contacto_soporte` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `descuento`
--

LOCK TABLES `descuento` WRITE;
/*!40000 ALTER TABLE `descuento` DISABLE KEYS */;
INSERT INTO `descuento` VALUES (1,'Nivel Plata','Descuento por nivel de fidelizacion Plata','PORCENTAJE',5.00,NULL,NULL,1),(2,'Nivel Oro','Descuento por nivel de fidelizacion Oro','PORCENTAJE',10.00,NULL,NULL,1),(3,'Nivel Platino','Descuento por nivel de fidelizacion Platino','PORCENTAJE',15.00,NULL,NULL,1);
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_compra`
--

LOCK TABLES `detalle_compra` WRITE;
/*!40000 ALTER TABLE `detalle_compra` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_factura`
--

LOCK TABLES `detalle_factura` WRITE;
/*!40000 ALTER TABLE `detalle_factura` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_pago_proveedor`
--

LOCK TABLES `detalle_pago_proveedor` WRITE;
/*!40000 ALTER TABLE `detalle_pago_proveedor` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `factura`
--

LOCK TABLES `factura` WRITE;
/*!40000 ALTER TABLE `factura` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `factura_descuento`
--

LOCK TABLES `factura_descuento` WRITE;
/*!40000 ALTER TABLE `factura_descuento` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimiento_inventario`
--

LOCK TABLES `movimiento_inventario` WRITE;
/*!40000 ALTER TABLE `movimiento_inventario` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `movimiento_punto`
--

LOCK TABLES `movimiento_punto` WRITE;
/*!40000 ALTER TABLE `movimiento_punto` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificacion`
--

LOCK TABLES `notificacion` WRITE;
/*!40000 ALTER TABLE `notificacion` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pago_personal`
--

LOCK TABLES `pago_personal` WRITE;
/*!40000 ALTER TABLE `pago_personal` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pago_proveedor`
--

LOCK TABLES `pago_proveedor` WRITE;
/*!40000 ALTER TABLE `pago_proveedor` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `persona`
--

LOCK TABLES `persona` WRITE;
/*!40000 ALTER TABLE `persona` DISABLE KEYS */;
INSERT INTO `persona` VALUES (1,'Ana','Propietaria',NULL,NULL,NULL,'admin@peluqueria.com',NULL,NULL,'2026-08-06 15:48:43'),(2,'Ana','Gimenez',NULL,NULL,'0981-000000','ana.cliente@example.com',NULL,NULL,'2026-08-06 15:48:43'),(10,'Distribuidora Capilar SA','',NULL,'80012345-0','021445566','ventas@capilar.com.py','Avda. Mariscal López 1234, Asunción',NULL,'2026-08-14 10:44:45'),(11,'Belleza Total SRL','',NULL,'80098765-1','021778899','pedidos@bellezatotal.py','Ruta Mcal. Estigarribia km 12, Luque',NULL,'2026-08-14 10:44:45'),(12,'Insumos del Este SA','',NULL,'80055443-3','021332211','contacto@insumoseste.py','Avda. España 890, Asunción',NULL,'2026-08-14 10:44:45'),(13,'Marta','Cáceres','3800111',NULL,'0981200100','marta.caceres@peluqueria.local',NULL,NULL,'2026-08-14 10:44:45'),(14,'Rocío','Duarte','3800222',NULL,'0981200200','rocio.duarte@peluqueria.local',NULL,NULL,'2026-08-14 10:44:45'),(15,'Lucía','Benítez','3800333',NULL,'0981200300','lucia.benitez@peluqueria.local',NULL,NULL,'2026-08-14 10:44:45'),(16,'Sofía','Espínola','3800444',NULL,'0981200400','sofia.espinola@peluqueria.local',NULL,NULL,'2026-08-14 10:44:45');
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
INSERT INTO `preferencia_usuario` VALUES (1,'claro',0,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `producto`
--

LOCK TABLES `producto` WRITE;
/*!40000 ALTER TABLE `producto` DISABLE KEYS */;
INSERT INTO `producto` VALUES (1,2,'Shampoo profesional 1L','Para lavado en el salón','unidad',85000.00,130000.00,10,1,1000.00,'ml'),(2,2,'Acondicionador 1L','Para lavado en el salón','unidad',80000.00,125000.00,10,1,1000.00,'ml'),(3,1,'Agua oxigenada 900ml','Revelador 20 volúmenes','unidad',35000.00,55000.00,10,1,900.00,'ml'),(4,1,'Tintura profesional','Tubo de 60 g, varios tonos','unidad',45000.00,70000.00,10,1,NULL,NULL),(5,2,'Ampolla de keratina','Sachet individual','unidad',18000.00,32000.00,10,1,NULL,NULL),(6,2,'Serum reparador 100ml','Puntas abiertas','unidad',40000.00,68000.00,10,1,NULL,NULL),(7,3,'Guantes de latex (caja)','Caja por 100 unidades','caja',38000.00,60000.00,10,1,NULL,NULL),(8,3,'Toallas descartables','Paquete por 50','paquete',25000.00,40000.00,10,1,NULL,NULL),(9,4,'Esmalte semipermanente','Frasco de 15 ml','unidad',22000.00,38000.00,10,1,NULL,NULL),(10,5,'Shampoo x 300ml (venta)','Para llevar','unidad',45000.00,85000.00,10,1,NULL,NULL);
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
INSERT INTO `producto_sucursal` VALUES (1,1,3.00,1),(2,1,3.00,1),(3,1,4.00,1),(4,1,6.00,1),(5,1,10.00,1),(6,1,5.00,1),(7,1,2.00,1),(8,1,3.00,1),(9,1,8.00,1),(10,1,5.00,1);
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `producto_utilizado`
--

LOCK TABLES `producto_utilizado` WRITE;
/*!40000 ALTER TABLE `producto_utilizado` DISABLE KEYS */;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedor`
--

LOCK TABLES `proveedor` WRITE;
/*!40000 ALTER TABLE `proveedor` DISABLE KEYS */;
INSERT INTO `proveedor` VALUES (4,'Marisa Duarte',1,10),(5,'Jorge Cabrera',1,11),(6,'Liliana Ayala',1,12);
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rol`
--

LOCK TABLES `rol` WRITE;
/*!40000 ALTER TABLE `rol` DISABLE KEYS */;
INSERT INTO `rol` VALUES (1,'Administrador','Acceso total al sistema, cuentas y configuración',1,1),(2,'Profesional','Empleado que atiende las citas del salón',1,1),(3,'Asistente administrativo','Operación diaria: citas, clientes, caja e inventario',1,1),(4,'Cliente','Acceso al portal del cliente',0,1);
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
INSERT INTO `rol_modulo` VALUES (2,'citas.agenda'),(2,'citas.atencion'),(2,'clientes.fidelizacion'),(2,'clientes.registro'),(2,'clientes.valoraciones'),(2,'facturacion.cobros'),(2,'facturacion.facturas'),(2,'seguridad.asistencia'),(3,'citas.agenda'),(3,'citas.atencion'),(3,'clientes.canjes'),(3,'clientes.fidelizacion'),(3,'clientes.registro'),(3,'clientes.valoraciones'),(3,'facturacion.caja'),(3,'facturacion.cobros'),(3,'facturacion.facturas'),(3,'facturacion.pagos'),(3,'facturacion.proveedores'),(3,'inventario.compras'),(3,'inventario.productos'),(3,'inventario.proveedores'),(3,'inventario.stock'),(3,'reportes'),(3,'seguridad.asistencia'),(3,'seguridad.turnos'),(3,'servicios.catalogo'),(3,'servicios.categorias'),(3,'servicios.descuentos');
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
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicio`
--

LOCK TABLES `servicio` WRITE;
/*!40000 ALTER TABLE `servicio` DISABLE KEYS */;
INSERT INTO `servicio` VALUES (1,1,'Corte de dama','Corte con lavado y peinado',75000.00,45,10,1,0),(2,1,'Corte de caballero','Corte clásico o con máquina',50000.00,30,10,1,0),(3,1,'Corte de niño','Hasta 12 años',40000.00,30,10,1,0),(4,4,'Brushing','Secado y modelado',60000.00,40,10,1,0),(5,4,'Peinado de fiesta','Recogido o semirecogido',120000.00,60,10,1,0),(6,2,'Coloración completa','Color de raíz a puntas',280000.00,120,10,1,1),(7,2,'Retoque de raíz','Sólo el crecimiento',150000.00,75,10,1,1),(8,2,'Mechas / balayage','Aclarado por mechones',350000.00,180,10,1,1),(9,3,'Lavado y acondicionado','Lavado con masaje',25000.00,20,10,1,0),(10,3,'Tratamiento capilar','Hidratación profunda',90000.00,50,10,1,1),(11,3,'Keratina','Alisado con keratina',400000.00,180,10,1,1),(12,5,'Manicura','Manos, esmaltado tradicional',45000.00,40,10,1,0),(13,5,'Manicura semipermanente','Esmaltado semipermanente',75000.00,60,10,1,0),(14,5,'Pedicura','Pies, esmaltado tradicional',55000.00,50,10,1,0),(15,6,'Depilación de cejas','Diseño y depilación',30000.00,20,10,1,0);
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicio_canjeable`
--

LOCK TABLES `servicio_canjeable` WRITE;
/*!40000 ALTER TABLE `servicio_canjeable` DISABLE KEYS */;
INSERT INTO `servicio_canjeable` VALUES (1,6,3000,30,1),(2,9,2000,30,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `servicio_realizado`
--

LOCK TABLES `servicio_realizado` WRITE;
/*!40000 ALTER TABLE `servicio_realizado` DISABLE KEYS */;
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
INSERT INTO `servicio_sucursal` VALUES (1,1),(2,1),(3,1),(4,1),(5,1),(6,1),(7,1),(8,1),(9,1),(10,1),(11,1),(12,1),(13,1),(14,1),(15,1);
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `timbrado`
--

LOCK TABLES `timbrado` WRITE;
/*!40000 ALTER TABLE `timbrado` DISABLE KEYS */;
INSERT INTO `timbrado` VALUES (4,1,1,'12345678','001','001','2026-08-14','2027-08-14',1,9999999,1),(5,1,5,'12345679','001','001','2026-08-14','2027-08-14',1,9999999,1),(6,1,8,'12345680','001','999','2026-08-14','2027-08-14',1,9999999,1);
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `token_cita`
--

LOCK TABLES `token_cita` WRITE;
/*!40000 ALTER TABLE `token_cita` DISABLE KEYS */;
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
INSERT INTO `turno_laboral` VALUES (3,1,'Turno Mañana','08:00:00','13:00:00',1),(4,1,'Turno Tarde','13:00:00','19:00:00',1);
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
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuario`
--

LOCK TABLES `usuario` WRITE;
/*!40000 ALTER TABLE `usuario` DISABLE KEYS */;
INSERT INTO `usuario` VALUES (1,1,1,'admin','$2y$10$aXqyrTtSHIcE7N.sPEA6xuI64h/JOM0/5frSbU5CuVp3qlQypgxgW','2026-07-14',1,'2026-07-14 19:42:29',1,NULL,NULL),(2,4,NULL,'cliente','$2y$10$pvpffhAjH9z6rqJfpekCSuwC.eFtu/j3iV883ICFOeZzeStA9P4DG',NULL,1,'2026-07-14 19:42:29',2,NULL,NULL),(10,2,1,'marta','$2y$12$Tl6dYeN6n5TYvdP/RZH6v.oll4BX3dAjKYnrewjAQ0fz7pWwT2jY6',NULL,1,'2026-08-14 10:44:45',13,NULL,NULL),(11,2,1,'rocio','$2y$12$Tl6dYeN6n5TYvdP/RZH6v.oll4BX3dAjKYnrewjAQ0fz7pWwT2jY6',NULL,1,'2026-08-14 10:44:45',14,NULL,NULL),(12,2,1,'lucia','$2y$12$Tl6dYeN6n5TYvdP/RZH6v.oll4BX3dAjKYnrewjAQ0fz7pWwT2jY6',NULL,1,'2026-08-14 10:44:45',15,NULL,NULL),(13,2,1,'sofia','$2y$12$Tl6dYeN6n5TYvdP/RZH6v.oll4BX3dAjKYnrewjAQ0fz7pWwT2jY6',NULL,1,'2026-08-14 10:44:45',16,NULL,NULL);
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
INSERT INTO `usuario_sucursal` VALUES (1,1),(10,1),(11,1),(12,1),(13,1);
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
INSERT INTO `usuario_turno` VALUES (10,3),(11,4),(12,3),(13,4);
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
-- Dumping events for database 'peluqueria_bd'
--

--
-- Dumping routines for database 'peluqueria_bd'
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

-- Dump completed on 2026-08-17  8:53:44
