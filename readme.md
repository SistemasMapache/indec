Sandbox pruebas scripts para asignación de domicilios a censistas - indec.


scripts:

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


