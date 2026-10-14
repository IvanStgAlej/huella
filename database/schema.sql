
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
DROP TABLE IF EXISTS `h_clientesconfig`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_clientesconfig` (
  `Id_Cliente` varchar(10) NOT NULL,
  `Literal` varchar(100) DEFAULT NULL,
  `color_primario` varchar(30) DEFAULT NULL,
  `color_secundario` varchar(30) DEFAULT NULL,
  `color_acento` varchar(30) DEFAULT NULL,
  `color_fondo` varchar(30) DEFAULT NULL,
  `color_fondo_card` varchar(30) DEFAULT NULL,
  `color_texto` varchar(30) DEFAULT NULL,
  `color_texto_suave` varchar(30) DEFAULT NULL,
  `color_borde` varchar(30) DEFAULT NULL,
  `fuente_titulo` varchar(200) DEFAULT NULL,
  `fuente_cuerpo` varchar(200) DEFAULT NULL,
  `tamanio_base` varchar(10) DEFAULT NULL,
  `max_ancho_contenido` varchar(20) DEFAULT NULL,
  `radio_tarjeta` varchar(20) DEFAULT NULL,
  `sombra_tarjeta` varchar(200) DEFAULT NULL,
  `nombre_producto` varchar(100) DEFAULT NULL,
  `logo_path` varchar(200) DEFAULT NULL,
  `logo_alt` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`Id_Cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_clientesusersgfc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_clientesusersgfc` (
  `Id_Cliente` varchar(10) NOT NULL,
  `Id_User` varchar(10) NOT NULL,
  `Empresa` varchar(3) DEFAULT NULL,
  PRIMARY KEY (`Id_Cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_dispositivos_baneados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_dispositivos_baneados` (
  `device_id` varchar(64) NOT NULL,
  `baneado_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_empresasconfig`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_empresasconfig` (
  `Id_Cliente` varchar(10) NOT NULL,
  `Empresa` varchar(3) NOT NULL,
  `Delegacion` varchar(3) NOT NULL,
  `Literal` varchar(3) DEFAULT NULL,
  `Id_Idioma` varchar(3) DEFAULT NULL,
  `AutorizarMuro_SN` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`Id_Cliente`,`Empresa`,`Delegacion`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_empresaslugareshabituales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_empresaslugareshabituales` (
  `Id_Cliente` varchar(10) NOT NULL,
  `Empresa` varchar(3) NOT NULL,
  `Id_Lugar` varchar(6) NOT NULL,
  `Lugar_Direccion` varchar(150) DEFAULT NULL,
  `Lugar_Ubicacion` text DEFAULT NULL,
  `Lugar_Teléfono` varchar(15) DEFAULT NULL,
  `Lugar_Foto1` text DEFAULT NULL,
  PRIMARY KEY (`Id_Cliente`,`Empresa`,`Id_Lugar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_empresastareasbaseconfig`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_empresastareasbaseconfig` (
  `Id_Cliente` varchar(10) NOT NULL,
  `Empresa` varchar(3) NOT NULL,
  `Id_TareaBase` varchar(6) NOT NULL,
  `Visible` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`Id_Cliente`,`Empresa`,`Id_TareaBase`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huella`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huella` (
  `Id_Huella` varchar(20) NOT NULL,
  `Id_Uso` varchar(4) DEFAULT NULL,
  `Id_Idioma` varchar(3) DEFAULT NULL,
  `Id_Cliente` varchar(10) DEFAULT NULL,
  `Id_Fallecido` varchar(10) DEFAULT NULL,
  `Empresa` varchar(3) DEFAULT NULL,
  `Delegacion` varchar(3) DEFAULT NULL,
  `CodExp` varchar(10) DEFAULT NULL,
  `link` varchar(300) DEFAULT NULL,
  `Ocultar` tinyint(1) DEFAULT NULL,
  `AutorizarMuro_SN` tinyint(1) DEFAULT NULL,
  `Aviso_Texto` text DEFAULT NULL,
  PRIMARY KEY (`Id_Huella`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huella_mensajes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huella_mensajes` (
  `Id_Mensaje` int(11) NOT NULL AUTO_INCREMENT,
  `Id_Huella` varchar(20) NOT NULL,
  `Msg_Titulo` varchar(150) DEFAULT '',
  `Msg_Texto` text DEFAULT NULL,
  `Msg_Orden` int(11) DEFAULT 0,
  `Msg_FechaCreacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`Id_Mensaje`),
  KEY `idx_huella` (`Id_Huella`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huellaactividades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huellaactividades` (
  `Id_Huella` varchar(20) NOT NULL,
  `Id_TareaBase` varchar(6) NOT NULL,
  `Empresa` varchar(3) NOT NULL,
  `Id_Lugar` char(10) NOT NULL,
  `Act_FechaHora_Inicio` datetime DEFAULT NULL,
  `Act_FechaHora_Fin` datetime DEFAULT NULL,
  `Act_Visible` tinyint(1) DEFAULT 0,
  `Act_Texto` text DEFAULT NULL,
  `Act_Sala` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`Id_Huella`,`Id_TareaBase`,`Empresa`,`Id_Lugar`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huellaactividadesasistencia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huellaactividadesasistencia` (
  `Id_Huella` varchar(20) NOT NULL,
  `Id_TareaBase` varchar(6) NOT NULL,
  `Id_User` varchar(10) NOT NULL,
  `Asiste_SN` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`Id_Huella`,`Id_TareaBase`,`Id_User`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huellafallecido`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huellafallecido` (
  `Id_Huella` varchar(20) NOT NULL,
  `Fallecido_Nombre` varchar(25) DEFAULT NULL,
  `Fallecido_Apellido_1` varchar(25) DEFAULT NULL,
  `Fallecido_Apellido_2` varchar(25) DEFAULT NULL,
  `Fallecido_Fecha_Nacimiento` datetime DEFAULT NULL,
  `Fallecido_Fecha_Defunción` datetime DEFAULT NULL,
  `Fallecido_Titulo` varchar(150) DEFAULT NULL,
  `Fallecido_Texto` text DEFAULT NULL,
  `Fallecido_Foto` text DEFAULT NULL,
  PRIMARY KEY (`Id_Huella`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huellapost`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huellapost` (
  `Id_Huella` varchar(20) NOT NULL,
  `Id_Post` varchar(6) NOT NULL,
  `Post_Id_user` varchar(10) NOT NULL,
  `Post_Autor` varchar(80) DEFAULT NULL,
  `Post_Parent_Id` varchar(6) DEFAULT NULL,
  `Post_Eliminado` tinyint(1) NOT NULL DEFAULT 0,
  `Post_Texto` text DEFAULT NULL,
  `Post_Link` text DEFAULT NULL,
  `Post_Fecha` datetime DEFAULT NULL,
  `Post_Vetado` tinyint(1) DEFAULT NULL,
  `Post_Autorizado` tinyint(1) DEFAULT NULL,
  `Post_Flag_Malsonante` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`Id_Huella`,`Id_Post`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huellapost_audios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huellapost_audios` (
  `Id_Audio` int(11) NOT NULL AUTO_INCREMENT,
  `Id_Huella` varchar(20) NOT NULL,
  `Id_Post` varchar(6) NOT NULL,
  `Audio_Ruta_Carpeta` varchar(300) NOT NULL COMMENT 'Ej: uploads/posts/H001/P001/audio/2025/05/15/',
  `Audio_Nombre` varchar(150) NOT NULL COMMENT 'Nombre del archivo: audio1.mp3',
  `Audio_Fecha` datetime DEFAULT NULL,
  PRIMARY KEY (`Id_Audio`),
  KEY `idx_post` (`Id_Huella`,`Id_Post`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huellapost_imagenes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huellapost_imagenes` (
  `Id_Imagen` int(11) NOT NULL AUTO_INCREMENT,
  `Id_Huella` varchar(20) NOT NULL,
  `Id_Post` varchar(6) NOT NULL,
  `Img_Ruta_Carpeta` varchar(300) NOT NULL COMMENT 'Ej: uploads/posts/H001/P001/img/2025/05/15/',
  `Img_Nombre` varchar(150) NOT NULL COMMENT 'Nombre del archivo: foto1.jpg',
  `Img_Orden` tinyint(4) NOT NULL DEFAULT 0,
  `Img_Fecha` datetime DEFAULT NULL,
  PRIMARY KEY (`Id_Imagen`),
  KEY `idx_post` (`Id_Huella`,`Id_Post`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huellapost_likes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huellapost_likes` (
  `Id_Huella` varchar(20) NOT NULL,
  `Id_Post` varchar(6) NOT NULL,
  `Like_Token` varchar(64) NOT NULL,
  `Like_Fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`Id_Huella`,`Id_Post`,`Like_Token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huellapost_videos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huellapost_videos` (
  `Id_Video` int(11) NOT NULL AUTO_INCREMENT,
  `Id_Huella` varchar(20) NOT NULL,
  `Id_Post` varchar(6) NOT NULL,
  `Video_Ruta_Carpeta` varchar(300) NOT NULL COMMENT 'Ej: uploads/posts/H001/P001/video/2025/05/15/',
  `Video_Nombre` varchar(150) NOT NULL COMMENT 'Nombre del archivo: video1.mp4',
  `Video_Fecha` datetime DEFAULT NULL,
  PRIMARY KEY (`Id_Video`),
  KEY `idx_post` (`Id_Huella`,`Id_Post`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huellapostcomments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huellapostcomments` (
  `Id_Huella` varchar(20) NOT NULL,
  `Id_Post` varchar(6) NOT NULL,
  `Id_Comment` varchar(3) NOT NULL,
  `Comment_Id_user` varchar(10) NOT NULL,
  `Comment_Texto` text DEFAULT NULL,
  `Comment_Vetado` tinyint(1) DEFAULT NULL,
  `Comment_Fecha` datetime DEFAULT NULL,
  PRIMARY KEY (`Id_Huella`,`Id_Post`,`Id_Comment`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huellapostcommentslikes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huellapostcommentslikes` (
  `Id_Huella` varchar(20) NOT NULL,
  `Id_Post` varchar(6) NOT NULL,
  `Id_Comment` varchar(3) NOT NULL,
  `LikeComment_Id_user` varchar(10) NOT NULL,
  `LikeComment_Fecha` datetime DEFAULT NULL,
  PRIMARY KEY (`Id_Huella`,`Id_Post`,`Id_Comment`,`LikeComment_Id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huellapostcommentsresponse`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huellapostcommentsresponse` (
  `Id_Huella` varchar(20) NOT NULL,
  `Id_Post` varchar(6) NOT NULL,
  `Id_Comment` varchar(3) NOT NULL,
  `Id_Response` varchar(3) NOT NULL,
  `Response_Id_user` varchar(10) NOT NULL,
  `Response_Texto` text DEFAULT NULL,
  `Response_Vetada` tinyint(1) DEFAULT NULL,
  `Response_Fecha` datetime DEFAULT NULL,
  PRIMARY KEY (`Id_Huella`,`Id_Post`,`Id_Comment`,`Id_Response`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huellapostcommentsresponselike`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huellapostcommentsresponselike` (
  `Id_Huella` varchar(20) NOT NULL,
  `Id_Post` varchar(6) NOT NULL,
  `Id_Comment` varchar(3) NOT NULL,
  `Id_Response` varchar(3) NOT NULL,
  `LikeResponse_Id_user` varchar(10) DEFAULT NULL,
  `LikeResponse_Fecha` datetime DEFAULT NULL,
  PRIMARY KEY (`Id_Huella`,`Id_Post`,`Id_Comment`,`Id_Response`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huellapostlikes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huellapostlikes` (
  `Id_Huella` varchar(20) NOT NULL,
  `Id_Post` varchar(6) NOT NULL,
  `LikePost_Id_user` varchar(10) NOT NULL,
  `LikePost_Fecha` datetime DEFAULT NULL,
  PRIMARY KEY (`Id_Huella`,`Id_Post`,`LikePost_Id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huellausersroles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huellausersroles` (
  `Id` int(11) NOT NULL AUTO_INCREMENT,
  `Id_Huella` varchar(20) NOT NULL,
  `User_Identificador` varchar(150) NOT NULL DEFAULT '',
  `Id_Rol` varchar(6) NOT NULL,
  `User_Parentesco` varchar(15) DEFAULT NULL,
  `User_Locked_SN` tinyint(1) DEFAULT NULL,
  `User_Nombre_Apellidos` varchar(100) NOT NULL DEFAULT '',
  `User_Password` varchar(255) NOT NULL DEFAULT '',
  `User_Baneado` tinyint(1) NOT NULL DEFAULT 0,
  `User_Device_Id` varchar(100) DEFAULT NULL,
  `User_Idioma` varchar(3) NOT NULL DEFAULT 'CAS',
  PRIMARY KEY (`Id`),
  UNIQUE KEY `unique_huella_user` (`Id_Huella`,`User_Identificador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_huellausersrolespermisos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_huellausersrolespermisos` (
  `Id_Huella` varchar(20) NOT NULL,
  `Id_User` varchar(50) NOT NULL,
  `Id_Rol` varchar(6) NOT NULL,
  `Id_Zona` varchar(7) NOT NULL,
  `Permiso_SN` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`Id_Huella`,`Id_User`,`Id_Rol`,`Id_Zona`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `h_users_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `h_users_backup` (
  `Id_User` int(11) NOT NULL AUTO_INCREMENT,
  `User_email` varchar(100) NOT NULL,
  `User_Alias` varchar(20) DEFAULT NULL,
  `User_Nombre_Apellidos` varchar(50) DEFAULT NULL,
  `User_tfno` varchar(15) DEFAULT NULL,
  `User_Password` varchar(255) DEFAULT NULL,
  `User_Idioma` varchar(3) DEFAULT NULL,
  `User_Device_Id` varchar(64) DEFAULT NULL,
  `User_Baneado` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`Id_User`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hs_idiomas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hs_idiomas` (
  `Id_Idioma` varchar(3) NOT NULL,
  `Idioma_Literal` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`Id_Idioma`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hs_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hs_roles` (
  `Id_Rol` varchar(6) NOT NULL,
  `Rol_Literal` varchar(15) DEFAULT NULL,
  `Rol_Descripción` varchar(75) DEFAULT NULL,
  PRIMARY KEY (`Id_Rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hs_tareasbase`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hs_tareasbase` (
  `Id_TareaBase` varchar(6) NOT NULL,
  `Literal` varchar(15) DEFAULT NULL,
  PRIMARY KEY (`Id_TareaBase`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hs_usos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `hs_usos` (
  `Id_Uso` varchar(4) NOT NULL,
  `Literal` varchar(100) DEFAULT NULL,
  `Descripcion` varchar(300) DEFAULT NULL,
  PRIMARY KEY (`Id_Uso`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

