
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES `hs_idiomas` WRITE;
/*!40000 ALTER TABLE `hs_idiomas` DISABLE KEYS */;
INSERT INTO `hs_idiomas` VALUES ('CAS','Castellano');
INSERT INTO `hs_idiomas` VALUES ('CAT','Catalán');
INSERT INTO `hs_idiomas` VALUES ('ENG','Inglés');
INSERT INTO `hs_idiomas` VALUES ('EUS','Euskera');
/*!40000 ALTER TABLE `hs_idiomas` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `hs_roles` WRITE;
/*!40000 ALTER TABLE `hs_roles` DISABLE KEYS */;
INSERT INTO `hs_roles` VALUES ('ADM','Administrador','Moderador');
INSERT INTO `hs_roles` VALUES ('COL','Colaborador','Participa en el Muro');
INSERT INTO `hs_roles` VALUES ('GFC','Cliente','Gestión Funerarias y Cementerios');
INSERT INTO `hs_roles` VALUES ('INV','Invitado','Recibe link');
INSERT INTO `hs_roles` VALUES ('OWN','Propietario','Desarrollador');
INSERT INTO `hs_roles` VALUES ('SOL','Solicitante','Solicitante');
/*!40000 ALTER TABLE `hs_roles` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `hs_tareasbase` WRITE;
/*!40000 ALTER TABLE `hs_tareasbase` DISABLE KEYS */;
INSERT INTO `hs_tareasbase` VALUES ('EXEQUI','Ceremonia');
INSERT INTO `hs_tareasbase` VALUES ('INCINE','Incineración');
INSERT INTO `hs_tareasbase` VALUES ('INHUMA','Inhumación');
INSERT INTO `hs_tareasbase` VALUES ('SALA_V','Sala Velatorio');
/*!40000 ALTER TABLE `hs_tareasbase` ENABLE KEYS */;
UNLOCK TABLES;

LOCK TABLES `hs_usos` WRITE;
/*!40000 ALTER TABLE `hs_usos` DISABLE KEYS */;
INSERT INTO `hs_usos` VALUES ('INFO','Info','Puede Ver Info  + Indicar ASISTEencia');
INSERT INTO `hs_usos` VALUES ('MURO','Info + Muro','Acceso completo: Ver Info + Red Social');
INSERT INTO `hs_usos` VALUES ('NADA','Ningún Uso','No se muestra Info');
/*!40000 ALTER TABLE `hs_usos` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

