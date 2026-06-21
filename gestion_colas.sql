-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: db
-- Tiempo de generación: 21-06-2026 a las 18:56:31
-- Versión del servidor: 11.3.2-MariaDB-1:11.3.2+maria~ubu2204
-- Versión de PHP: 8.2.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `gestion_colas`
--
CREATE DATABASE IF NOT EXISTS `gestion_colas` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `gestion_colas`;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `asignaturas`
--

CREATE TABLE `asignaturas` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `cola_abierta` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `incidencias_acceso`
--

CREATE TABLE `incidencias_acceso` (
  `id` int(11) NOT NULL,
  `email_infractor` varchar(150) NOT NULL,
  `nombre_infractor` varchar(100) NOT NULL,
  `fecha_incidencia` datetime DEFAULT current_timestamp(),
  `ip_origen` varchar(45) NOT NULL,
  `pagina_intentada` varchar(50) DEFAULT 'gestion.php'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `turnos`
--

CREATE TABLE `turnos` (
  `id` int(11) NOT NULL,
  `asignatura_id` int(11) DEFAULT NULL,
  `nombre_alumno` varchar(100) NOT NULL,
  `codigo_alumno` varchar(50) NOT NULL,
  `email_alumno` varchar(150) NOT NULL,
  `turno_numero` int(11) NOT NULL,
  `posicion_cola` int(11) NOT NULL,
  `estado` enum('esperando','atendiendo','atendido','cancelado') DEFAULT 'esperando',
  `resultat_prova` enum('pendent','apte','no_apte') DEFAULT 'pendent',
  `fecha_registro` datetime DEFAULT current_timestamp(),
  `hora_inicio_atencion` datetime DEFAULT NULL,
  `hora_fin_atencion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `asignaturas`
--
ALTER TABLE `asignaturas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `incidencias_acceso`
--
ALTER TABLE `incidencias_acceso`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `turnos`
--
ALTER TABLE `turnos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `asignatura_id` (`asignatura_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `asignaturas`
--
ALTER TABLE `asignaturas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `incidencias_acceso`
--
ALTER TABLE `incidencias_acceso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `turnos`
--
ALTER TABLE `turnos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `turnos`
--
ALTER TABLE `turnos`
  ADD CONSTRAINT `turnos_ibfk_1` FOREIGN KEY (`asignatura_id`) REFERENCES `asignaturas` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
