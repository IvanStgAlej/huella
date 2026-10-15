-- ============================================================================
--  Huella · datos de demo
--  Todo es FICTICIO: funerarias, personas, direcciones, teléfonos y mensajes.
--  Requiere schema.sql y reference_data.sql. Se puede ejecutar varias veces.
--  Imágenes: public/uploads/demo/
--  Acceso de administrador: demo@huella.test / demo1234
--    http://localhost/huella/carmen-ruiz-navarro   (castellano, tema cálido)
--    http://localhost/huella/josep-vidal-soler     (catalán, tema azul)
-- ============================================================================

SET NAMES utf8mb4;
START TRANSACTION;

-- Limpieza de una carga anterior de la demo
DELETE FROM `h_huellapost_likes` WHERE `Id_Huella` IN ('9900000001202600001', '9900000002202600002');
DELETE FROM `h_huellapost_imagenes` WHERE `Id_Huella` IN ('9900000001202600001', '9900000002202600002');
DELETE FROM `h_huellapost` WHERE `Id_Huella` IN ('9900000001202600001', '9900000002202600002');
DELETE FROM `h_huellaactividadesasistencia` WHERE `Id_Huella` IN ('9900000001202600001', '9900000002202600002');
DELETE FROM `h_huellaactividades` WHERE `Id_Huella` IN ('9900000001202600001', '9900000002202600002');
DELETE FROM `h_huella_mensajes` WHERE `Id_Huella` IN ('9900000001202600001', '9900000002202600002');
DELETE FROM `h_huellausersroles` WHERE `Id_Huella` IN ('9900000001202600001', '9900000002202600002');
DELETE FROM `h_huellafallecido` WHERE `Id_Huella` IN ('9900000001202600001', '9900000002202600002');
DELETE FROM `h_huella` WHERE `Id_Huella` IN ('9900000001202600001', '9900000002202600002');
DELETE FROM `h_empresaslugareshabituales` WHERE `Id_Cliente` IN ('9900000001', '9900000002');
DELETE FROM `h_empresasconfig` WHERE `Id_Cliente` IN ('9900000001', '9900000002');
DELETE FROM `h_clientesconfig` WHERE `Id_Cliente` IN ('9900000001', '9900000002');

-- Clientes (funerarias) y su tema visual: multitenant
INSERT INTO `h_clientesconfig` (`Id_Cliente`, `Literal`, `color_primario`, `color_secundario`, `color_acento`, `color_fondo`, `color_fondo_card`, `color_texto`, `color_texto_suave`, `color_borde`, `fuente_titulo`, `fuente_cuerpo`, `tamanio_base`, `max_ancho_contenido`, `radio_tarjeta`, `sombra_tarjeta`, `nombre_producto`, `logo_path`, `logo_alt`) VALUES
  ('9900000001', 'Funeraria Demo', '#3d2b1f', '#7a5c4e', '#c4a882', '#f7f4f1', '#ffffff', '#2c2c2c', '#6b6b6b', '#e0d8d0', '''Georgia'', ''Times New Roman'', serif', '''Segoe UI'', ''Helvetica Neue'', Arial, sans-serif', '16px', '480px', '16px', '0 2px 12px rgba(0,0,0,0.08)', 'Huella', NULL, NULL),
  ('9900000002', 'Tanatori Mar Blau', '#1f4e6b', '#3d7ea6', '#8fc1d9', '#eef5f9', '#ffffff', '#1b2a33', '#5b7686', '#c9dde8', '''Trebuchet MS'', ''Segoe UI'', sans-serif', '''Segoe UI'', ''Helvetica Neue'', Arial, sans-serif', '16px', '480px', '20px', '0 4px 16px rgba(31,78,107,0.10)', 'Huella', NULL, NULL);

INSERT INTO `h_empresasconfig` (`Id_Cliente`, `Empresa`, `Delegacion`, `Literal`, `Id_Idioma`, `AutorizarMuro_SN`) VALUES
  ('9900000001', '001', '000', 'DM', 'CAS', 1),
  ('9900000002', '001', '000', 'MB', 'CAT', 1);

-- Lugares habituales de cada funeraria (direcciones inventadas)
INSERT INTO `h_empresaslugareshabituales` (`Id_Cliente`, `Empresa`, `Id_Lugar`, `Lugar_Direccion`, `Lugar_Ubicacion`, `Lugar_Teléfono`, `Lugar_Foto1`) VALUES
  ('9900000001', '001', 'LUG001', 'Tanatorio Demo — C/ de la Esperanza, 12, Madrid', 'https://maps.google.com/?q=40.4168,-3.7038', '910 000 001', NULL),
  ('9900000001', '001', 'LUG002', 'Parroquia de San Ejemplo — Plaza Mayor, 3, Madrid', 'https://maps.google.com/?q=40.4155,-3.7074', '910 000 002', NULL),
  ('9900000001', '001', 'LUG003', 'Cementerio Municipal Demo — Camino del Ciprés, s/n, Madrid', 'https://maps.google.com/?q=40.4200,-3.6600', '910 000 003', NULL),
  ('9900000002', '001', 'LUG001', 'Tanatori Mar Blau — Passeig del Mar, 8, Barcelona', 'https://maps.google.com/?q=41.3851,2.1734', '930 000 001', NULL),
  ('9900000002', '001', 'LUG002', 'Crematori Demo — Carrer dels Xiprers, 4, Barcelona', 'https://maps.google.com/?q=41.3700,2.1500', '930 000 002', NULL);

-- Huellas (páginas memoriales)
INSERT INTO `h_huella` (`Id_Huella`, `Id_Uso`, `Id_Idioma`, `Id_Cliente`, `Id_Fallecido`, `Empresa`, `Delegacion`, `CodExp`, `link`, `Ocultar`, `AutorizarMuro_SN`, `Aviso_Texto`) VALUES
  ('9900000001202600001', 'MURO', 'CAS', '9900000001', '202600001', '001', '000', '2026000001', 'carmen-ruiz-navarro', 0, 1, 'La familia agradece de corazón todas las muestras de cariño. El velatorio permanecerá abierto hasta las 22:00.'),
  ('9900000002202600002', 'MURO', 'CAT', '9900000002', '202600002', '001', '000', '2026000002', 'josep-vidal-soler', 0, 1, NULL);

INSERT INTO `h_huellafallecido` (`Id_Huella`, `Fallecido_Nombre`, `Fallecido_Apellido_1`, `Fallecido_Apellido_2`, `Fallecido_Fecha_Nacimiento`, `Fallecido_Fecha_Defunción`, `Fallecido_Titulo`, `Fallecido_Texto`, `Fallecido_Foto`) VALUES
  ('9900000001202600001', 'Carmen', 'Ruiz', 'Navarro', '1941-03-08 00:00:00', '2026-10-12 00:00:00', 'Maestra, abuela y amante del campo', 'Carmen nació en un pueblo de olivares y nunca dejó de llevarlo consigo. Fue maestra de primaria durante más de treinta años y muchos de sus alumnos aún recuerdan sus dictados de los lunes y sus excursiones al río.\n\nLe gustaba cuidar el huerto, leer poesía en voz alta y reunir a toda la familia los domingos alrededor de una mesa siempre demasiado pequeña. Deja tres hijos, siete nietos y una casa llena de libros.', 'uploads/demo/fallecidos/carmen.jpg'),
  ('9900000002202600002', 'Josep', 'Vidal', 'Soler', '1950-07-21 00:00:00', '2026-10-10 00:00:00', 'Pescador, pare i avi', 'En Josep va passar la vida entre el mar i la família. Cada matinada sortia a pescar amb la seva barca, la «Rosa», i a la tarda explicava històries al port a qui el volgués escoltar.\n\nEns deixa el record del seu somriure tranquil i de les seves receptes de suquet de peix.', 'uploads/demo/fallecidos/josep.jpg');

-- Actos del funeral
INSERT INTO `h_huellaactividades` (`Id_Huella`, `Id_TareaBase`, `Empresa`, `Id_Lugar`, `Act_FechaHora_Inicio`, `Act_FechaHora_Fin`, `Act_Visible`, `Act_Texto`, `Act_Sala`) VALUES
  ('9900000001202600001', 'SALA_V', '001', 'LUG001', '2026-10-13 16:00:00', '2026-10-14 22:00:00', 1, 'La familia estará acompañando a Carmen durante todo el día.', 'Sala 2'),
  ('9900000001202600001', 'EXEQUI', '001', 'LUG002', '2026-10-15 11:00:00', '2026-10-15 12:00:00', 1, 'Misa funeral.', NULL),
  ('9900000001202600001', 'INHUMA', '001', 'LUG003', '2026-10-15 12:30:00', '2026-10-15 13:30:00', 1, 'Acto íntimo con la familia más cercana.', NULL),
  ('9900000002202600002', 'SALA_V', '001', 'LUG001', '2026-10-11 10:00:00', '2026-10-12 20:00:00', 1, NULL, 'Sala 1'),
  ('9900000002202600002', 'INCINE', '001', 'LUG002', '2026-10-13 10:00:00', '2026-10-13 11:00:00', 1, 'Cerimònia de comiat oberta a tothom.', NULL);

-- Tablón: mensajes de la familia
INSERT INTO `h_huella_mensajes` (`Id_Huella`, `Msg_Titulo`, `Msg_Texto`, `Msg_Orden`, `Msg_FechaCreacion`) VALUES
  ('9900000001202600001', 'Gracias', 'Queremos dar las gracias a todas las personas que nos habéis acompañado estos días. Vuestras palabras y recuerdos nos ayudan a seguir adelante.', 1, '2026-10-13 09:00:00'),
  ('9900000001202600001', 'Los versos que le gustaba recitar', 'Quedan los olivos,\nqueda la tarde dorada,\nqueda tu voz en la casa\ncomo una luz encendida.', 2, '2026-10-13 09:05:00'),
  ('9900000002202600002', 'Gràcies', 'Moltes gràcies per tot el suport i l''estima que ens heu fet arribar.', 1, '2026-10-11 12:00:00');

-- Usuarios. Acceso de administrador de demo: demo@huella.test / demo1234
-- (el resto tiene contraseñas aleatorias que no se pueden usar)
INSERT INTO `h_huellausersroles` (`Id`, `Id_Huella`, `User_Identificador`, `Id_Rol`, `User_Parentesco`, `User_Locked_SN`, `User_Nombre_Apellidos`, `User_Password`, `User_Baneado`, `User_Device_Id`, `User_Idioma`) VALUES
  (1001, '9900000001202600001', 'demo@huella.test', 'ADM', 'Hija', 0, 'Lucía Ruiz', '$2y$10$mCNGy4locz9oNLlaDUyOmenocN/lqMOrLTjsL/u0qj907laXV3Syi', 0, NULL, 'CAS'),
  (1002, '9900000001202600001', 'ana.martin@example.com', 'COL', NULL, 0, 'Ana Martín', '$2y$10$QGzOB0ZXn5KaodePiHruZeX4zSy0DtLggbTLxOSlGSu.RpclEkbTa', 0, NULL, 'CAS'),
  (1003, '9900000001202600001', 'pablo.gomez@example.com', 'COL', NULL, 0, 'Pablo Gómez', '$2y$10$BjmffPLt6TndsmljFX/uL.5N0pokQv1JQxQBX9g3rQtJevren2hHO', 0, NULL, 'CAS'),
  (1004, '9900000001202600001', 'elena.ruiz@example.com', 'COL', 'Nieta', 0, 'Elena Ruiz', '$2y$10$BtbFIpFryzafP9mpgUlpu.74lDSp92VXZiIVD1q3QFBpkwkmv6mYa', 0, NULL, 'CAS'),
  (1005, '9900000001202600001', 'antonio.sanz@example.com', 'COL', NULL, 0, 'Antonio Sanz', '$2y$10$Fxbblz8cEwTcbdg/WdFnc.XcQODpajS1B9JEdjp6A6tLAGpqxnFQm', 0, NULL, 'CAS'),
  (1006, '9900000001202600001', '600000006', 'INV', NULL, 0, 'Familia Navarro', '$2y$10$PP65DjVnKbi/1sTtRVI.Vuj.qe0zdrqfS925ph7ycWGdX.i83oWzy', 0, NULL, 'CAS'),
  (1101, '9900000002202600002', 'demo@huella.test', 'ADM', 'Filla', 0, 'Marta Vidal', '$2y$10$mCNGy4locz9oNLlaDUyOmenocN/lqMOrLTjsL/u0qj907laXV3Syi', 0, NULL, 'CAT'),
  (1102, '9900000002202600002', 'jordi.puig@example.com', 'COL', NULL, 0, 'Jordi Puig', '$2y$10$YB827dsyWIj.L6KRKGqCV.eq9PWKGXyWKodKpZxKujSp6u.1mxdTO', 0, NULL, 'CAT'),
  (1103, '9900000002202600002', 'nuria.ferrer@example.com', 'COL', NULL, 0, 'Núria Ferrer', '$2y$10$SQAYuAVDYUW.hJLwBZcNK.8YVdBSVNKxnInmqXVJTTIf/DEQafa9u', 0, NULL, 'CAT');

INSERT INTO `h_huellaactividadesasistencia` (`Id_Huella`, `Id_TareaBase`, `Id_User`, `Asiste_SN`) VALUES
  ('9900000001202600001', 'SALA_V', '1002', 1),
  ('9900000001202600001', 'SALA_V', '1003', 1),
  ('9900000001202600001', 'SALA_V', '1005', 1),
  ('9900000001202600001', 'EXEQUI', '1002', 1),
  ('9900000001202600001', 'EXEQUI', '1004', 1),
  ('9900000001202600001', 'EXEQUI', '1006', 0),
  ('9900000001202600001', 'INHUMA', '1004', 1),
  ('9900000002202600002', 'INCINE', '1102', 1),
  ('9900000002202600002', 'INCINE', '1103', 1);

-- Muro de condolencias
INSERT INTO `h_huellapost` (`Id_Huella`, `Id_Post`, `Post_Id_user`, `Post_Autor`, `Post_Parent_Id`, `Post_Eliminado`, `Post_Texto`, `Post_Link`, `Post_Fecha`, `Post_Vetado`, `Post_Autorizado`, `Post_Flag_Malsonante`) VALUES
  ('9900000001202600001', 'DEMO01', '1002', 'Ana Martín', NULL, 0, 'Carmen fue mi maestra en tercero. Gracias a ella me enamoré de los libros. Un abrazo enorme a toda la familia.', NULL, '2026-10-12 19:40:00', 0, 1, 0),
  ('9900000001202600001', 'DEMO02', '1003', 'Pablo Gómez', NULL, 0, 'Siempre la recordaré paseando por el paseo marítimo cada verano, saludando a todo el mundo. Descansa en paz, Carmen.', NULL, '2026-10-12 21:05:00', 0, 1, 0),
  ('9900000001202600001', 'DEMO03', '1004', 'Elena Ruiz', NULL, 0, 'Abuela, gracias por enseñarme a mirar las cosas despacio, por las tardes en el huerto y por todas las historias. Te vamos a echar muchísimo de menos.', NULL, '2026-10-13 08:30:00', 0, 1, 0),
  ('9900000001202600001', 'DEMO04', '1005', 'Antonio Sanz', NULL, 0, 'Mi más sentido pésame. Fue una gran vecina y mejor persona.', NULL, '2026-10-13 10:15:00', 0, 1, 0),
  ('9900000001202600001', 'DEMO05', '1001', 'Lucía Ruiz', 'DEMO01', 0, 'Gracias, Ana. Ella también hablaba mucho de aquella clase.', NULL, '2026-10-13 11:00:00', 0, 1, 0),
  ('9900000001202600001', 'DEMO06', '1003', 'Pablo Gómez', 'DEMO03', 0, 'Qué palabras tan bonitas, Elena. Un abrazo fuerte.', NULL, '2026-10-13 12:20:00', 0, 1, 0),
  ('9900000002202600002', 'DEMO11', '1102', 'Jordi Puig', NULL, 0, 'Sempre et recordarem al port, Josep. Bon vent i barca nova.', NULL, '2026-10-10 20:00:00', 0, 1, 0),
  ('9900000002202600002', 'DEMO12', '1103', 'Núria Ferrer', NULL, 0, 'Una abraçada molt forta a tota la família.', NULL, '2026-10-11 09:30:00', 0, 1, 0);

INSERT INTO `h_huellapost_imagenes` (`Id_Huella`, `Id_Post`, `Img_Ruta_Carpeta`, `Img_Nombre`, `Img_Orden`, `Img_Fecha`) VALUES
  ('9900000001202600001', 'DEMO01', 'uploads/demo/posts/', 'flores.jpg', 0, '2026-10-12 19:40:00'),
  ('9900000001202600001', 'DEMO02', 'uploads/demo/posts/', 'paseo.jpg', 0, '2026-10-12 21:05:00'),
  ('9900000001202600001', 'DEMO03', 'uploads/demo/posts/', 'vela.jpg', 0, '2026-10-13 08:30:00');

INSERT INTO `h_huellapost_likes` (`Id_Huella`, `Id_Post`, `Like_Token`, `Like_Fecha`) VALUES
  ('9900000001202600001', 'DEMO01', 'demo-like-demo01-1', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO01', 'demo-like-demo01-2', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO01', 'demo-like-demo01-3', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO01', 'demo-like-demo01-4', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO01', 'demo-like-demo01-5', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO02', 'demo-like-demo02-1', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO02', 'demo-like-demo02-2', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO02', 'demo-like-demo02-3', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO03', 'demo-like-demo03-1', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO03', 'demo-like-demo03-2', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO03', 'demo-like-demo03-3', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO03', 'demo-like-demo03-4', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO03', 'demo-like-demo03-5', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO03', 'demo-like-demo03-6', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO03', 'demo-like-demo03-7', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO03', 'demo-like-demo03-8', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO04', 'demo-like-demo04-1', '2026-10-13 13:00:00'),
  ('9900000001202600001', 'DEMO04', 'demo-like-demo04-2', '2026-10-13 13:00:00'),
  ('9900000002202600002', 'DEMO11', 'demo-like-demo11-1', '2026-10-13 13:00:00'),
  ('9900000002202600002', 'DEMO11', 'demo-like-demo11-2', '2026-10-13 13:00:00'),
  ('9900000002202600002', 'DEMO11', 'demo-like-demo11-3', '2026-10-13 13:00:00'),
  ('9900000002202600002', 'DEMO11', 'demo-like-demo11-4', '2026-10-13 13:00:00');

COMMIT;
