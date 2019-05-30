Sandbox pruebas scripts para asignación de domicilios a censistas - indec.


scripts:

***

**recuperamanzanas01.php**

ingresando un fr ( fraccionradio, ej:0108) devuelve:
- las manzanas ordenadas del radio por adyacencia
	por manzana:
	- el id de fraccionradio
	- la manzana actual
	- la manzana siguiente
	- el linestring de adyacencia entre manzactual - manzsig
	- el punto de interseccion entre:
		- manzactual y manzsig
		- manzactual y boundary del radio
	- el id del punto tras stdump de manzactual
	- el id del punto tras stdump de manzasig 

***

**recuperamanzanas02.php**

Objetivo del script: intentar clasificar atributos entre adyacentes a una manzana actual y las candidatas siguientes.

Dado un conjunto de prov||depto||codloc||frac||radio , el script devuelve:
- id prov||depto||codloc||frac||radio del radio
- linestring boundary exterior del radio
- cantidad de manzanas que tocan boundary
- cantidad de manzanas que no tocan boundary
- bool si el radio contiene manzanas que no tocan boundary
- para los radios con bool true (solo radios cuyo total de manzanas tocan boundary del radio):
    - mzaid (prov||depto||codloc||frac||radio|mza)
    - tipo de intersección entre manzana y boundary
    - por manzana adyacente a manzana actual
        - id de manzanas adyacentes a manzana actual
        - distancia de manzanas adyacentes y manzana actual



***

**topologia_routing.sql**

Recorrido desde una perspectiva de funciones de topologia ChPP y Dijkstra para el recorrido de segmentos

**geom_recorridomanzanas_linestringboundary.sql**

test de recorrido de manzanas por valor 0-1 del linestring del radio y sus manzanas adyacentes
