

-- test de recorrido de manzanas por valor 0-1 del linestring del radio y sus manzanas adyacentes:

--  float between 0 and 1 representing the location of the closest point on LineString to the given Point

-- el objetivo de esta query es obtener un orden de manzanas siguiendo  a traves de las funciones :
-- ST_LineInterpolatePoint
-- https://postgis.net/docs/ST_LineLocatePoint.html
-- ST_LineInterpolatePoint
-- https://postgis.net/docs/ST_LineInterpolatePoint.html

    WITH fracradios AS (


    
      SELECT
      prov||depto||codloc||frac||radio as boundary_fracradio,
      ST_CollectionHomogenize((ST_Boundary(ST_Union(geom)))) boundary_geom,
      ST_AsText(ST_CollectionHomogenize((ST_Boundary(ST_Union(geom))))) boundary_geom_astext

      FROM indec_e0211poligono
      GROUP BY prov||depto||codloc||frac||radio
      having prov||depto||codloc||frac||radio in (
      --'020110100707'
      '020110101504'
      ) -- las que cumplen con total mzas en boundary

      
      
    )
    
      SELECT DISTINCT

	prov||depto||codloc||frac||radio||mza as mzaid,

	-- posicion en orden de linea boundary
	ST_LineLocatePoint(fracradios.boundary_geom, ST_LineInterpolatePoint(ST_GeometryN(ST_CollectionExtract(st_intersection(fracradios.boundary_geom,indec_e0211poligono.geom),2),1), 0.25)),

	-- primera linea de intersects
	ST_GeometryN(ST_CollectionExtract(st_intersection(fracradios.boundary_geom,indec_e0211poligono.geom),2),1) intersection_boundary_mza

      FROM indec_e0211poligono 
      join fracradios 
	on prov||depto||codloc||frac||radio = boundary_fracradio and
	 st_intersects(fracradios.boundary_geom,indec_e0211poligono.geom) 

	ORDER BY
	ST_LineLocatePoint(fracradios.boundary_geom, ST_LineInterpolatePoint(ST_GeometryN(ST_CollectionExtract(st_intersection(fracradios.boundary_geom,indec_e0211poligono.geom),2),1), 0.25))

