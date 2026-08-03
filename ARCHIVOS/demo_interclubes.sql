-- DEMO INTERCLUBES (evento 16) — datos ficticios para desarrollo visual
-- Limpieza: DELETE por id_evento=16 en _ic_*/_p_incripciones/_p_clubes/_relacion_evento_categoria;
--           _p_usuarios WHERE medio='demo-ic'; _p_eventos WHERE id=16.

INSERT INTO _p_eventos (id, codigo_evento, evento, url_amigable, estado, id_tipo_evento, fecha, url_fixture, boton_fixture, boton_llaves, descripcion)
VALUES (16, 'DEMO16', 'DEMO INTERCLUBES', 'demo-interclubes', 'previsualizacion', 5, '2026-08-15', 'grafico-interclubes', 'visible', 'visible', '');

INSERT INTO _relacion_evento_categoria (id_evento, id_categoria, sexo, estado, orden_visualizacion, max_parejas)
VALUES (16, 25, 'hombre', 'activo', 1, 2);

INSERT INTO _p_clubes (id, id_evento, nombre, responsable, celular, email, token, estado) VALUES
(101, 16, 'AREA 4',          'Demo', '0981000001', '', MD5('demo-club-101'), 'activo'),
(102, 16, 'LUJINI',          'Demo', '0981000002', '', MD5('demo-club-102'), 'activo'),
(103, 16, 'VISTA BAR',       'Demo', '0981000003', '', MD5('demo-club-103'), 'activo'),
(104, 16, 'MOES-YOYI',       'Demo', '0981000004', '', MD5('demo-club-104'), 'activo'),
(105, 16, 'ARENA BAR',       'Demo', '0981000005', '', MD5('demo-club-105'), 'activo'),
(106, 16, 'EN LO DE CHIQUI', 'Demo', '0981000006', '', MD5('demo-club-106'), 'activo');

INSERT INTO _p_usuarios (nombre, apellido, cel, ci, sexo, tipo_documento, registro, tipo, medio) VALUES
('Bruno',   'Acosta',    '0981', '9200001', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Diego',   'Benitez',   '0981', '9200002', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Marcos',  'Cabrera',   '0981', '9200003', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Ivan',    'Duarte',    '0981', '9200004', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Pablo',   'Estigarribia','0981','9200005','hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Hugo',    'Fernandez', '0981', '9200006', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Cesar',   'Gimenez',   '0981', '9200007', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Rodrigo', 'Lopez',     '0981', '9200008', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Oscar',   'Martinez',  '0981', '9200009', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Fabio',   'Nunez',     '0981', '9200010', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Adrian',  'Ortiz',     '0981', '9200011', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Nelson',  'Paredes',   '0981', '9200012', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Sergio',  'Quintana',  '0981', '9200013', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Tomas',   'Rios',      '0981', '9200014', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Ariel',   'Sosa',      '0981', '9200015', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Victor',  'Torres',    '0981', '9200016', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Willy',   'Vera',      '0981', '9200017', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Junior',  'Zarate',    '0981', '9200018', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Matias',  'Aguilar',   '0981', '9200019', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Leo',     'Barrios',   '0981', '9200020', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Gustavo', 'Caceres',   '0981', '9200021', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Ramon',   'Delgado',   '0981', '9200022', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Enzo',    'Espinola',  '0981', '9200023', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Franco',  'Flores',    '0981', '9200024', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Kike',    'Suarez',    '0981', '9200025', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic'),
('Beto',    'Ramirez',   '0981', '9200026', 'hombre', 'CI', 'inscripcion', 'jugador', 'demo-ic');

-- Inscripciones espejo (a,b)+(b,a): 2 parejas por club. Orden de insert = Pareja 1, Pareja 2.
INSERT INTO _p_incripciones (id_evento, ci, ci_dupla, id_categoria, id_club, estado, phorario, comentario, medio) VALUES
(16,'9200001','9200002',25,101,'inscripto','no','','demo-ic'),(16,'9200002','9200001',25,101,'inscripto','no','','demo-ic'),
(16,'9200003','9200004',25,101,'inscripto','no','','demo-ic'),(16,'9200004','9200003',25,101,'inscripto','no','','demo-ic'),
(16,'9200005','9200006',25,102,'inscripto','no','','demo-ic'),(16,'9200006','9200005',25,102,'inscripto','no','','demo-ic'),
(16,'9200007','9200008',25,102,'inscripto','no','','demo-ic'),(16,'9200008','9200007',25,102,'inscripto','no','','demo-ic'),
(16,'9200009','9200010',25,103,'inscripto','no','','demo-ic'),(16,'9200010','9200009',25,103,'inscripto','no','','demo-ic'),
(16,'9200011','9200012',25,103,'inscripto','no','','demo-ic'),(16,'9200012','9200011',25,103,'inscripto','no','','demo-ic'),
(16,'9200013','9200014',25,104,'inscripto','no','','demo-ic'),(16,'9200014','9200013',25,104,'inscripto','no','','demo-ic'),
(16,'9200015','9200016',25,104,'inscripto','no','','demo-ic'),(16,'9200016','9200015',25,104,'inscripto','no','','demo-ic'),
(16,'9200017','9200018',25,105,'inscripto','no','','demo-ic'),(16,'9200018','9200017',25,105,'inscripto','no','','demo-ic'),
(16,'9200019','9200020',25,105,'inscripto','no','','demo-ic'),(16,'9200020','9200019',25,105,'inscripto','no','','demo-ic'),
(16,'9200021','9200022',25,106,'inscripto','no','','demo-ic'),(16,'9200022','9200021',25,106,'inscripto','no','','demo-ic'),
(16,'9200023','9200024',25,106,'inscripto','no','','demo-ic'),(16,'9200024','9200023',25,106,'inscripto','no','','demo-ic');

-- Suplentes (feature nueva): AREA 4 y MOES-YOYI tienen suplente
INSERT INTO _ic_suplentes (id_evento, id_categoria, id_club, ci) VALUES
(16, 25, 101, '9200025'),
(16, 25, 104, '9200026');

-- Sorteo: G1 = AREA 4, LUJINI, VISTA BAR · G2 = MOES-YOYI, ARENA BAR, EN LO DE CHIQUI
INSERT INTO _ic_sorteo (id_evento, id_categoria, id_club, grupo, posicion) VALUES
(16,25,101,1,1),(16,25,102,1,2),(16,25,103,1,3),
(16,25,104,2,1),(16,25,105,2,2),(16,25,106,2,3);

-- Partidos con estados mixtos:
-- G1 · AREA 4 vs LUJINI: serie FINALIZADA 2-0
INSERT INTO _ic_partidos (id_evento,id_categoria,grupo,fase,club1,club2,es_desempate,en_juego,ci1_a,ci1_b,ci2_a,ci2_b,s1c1,s1c2,s2c1,s2c2,s3c1,s3c2) VALUES
(16,25,1,'grupo',101,102,0,'no','9200001','9200002','9200005','9200006',6,3,6,4,0,0),
(16,25,1,'grupo',101,102,0,'no','9200003','9200004','9200007','9200008',6,2,3,6,7,5),
-- G1 · AREA 4 vs VISTA BAR: serie 1-1 → NECESITA DESEMPATE (playground del definidor)
(16,25,1,'grupo',101,103,0,'no','9200001','9200002','9200009','9200010',6,4,6,4,0,0),
(16,25,1,'grupo',101,103,0,'no','9200003','9200004','9200011','9200012',3,6,4,6,0,0),
-- G1 · LUJINI vs VISTA BAR: P1 EN JUEGO (sin resultado)
(16,25,1,'grupo',102,103,0,'si','9200005','9200006','9200009','9200010',0,0,0,0,0,0),
-- G2 · MOES-YOYI vs ARENA BAR: 1-1 + desempate MEZCLADO jugado → serie 2-1 FINALIZADA
(16,25,2,'grupo',104,105,0,'no','9200013','9200014','9200017','9200018',6,1,6,2,0,0),
(16,25,2,'grupo',104,105,0,'no','9200015','9200016','9200019','9200020',3,6,6,4,5,7),
(16,25,2,'grupo',104,105,1,'no','9200013','9200015','9200017','9200019',7,5,4,6,10,8);
