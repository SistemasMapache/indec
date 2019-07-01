<?php

header('Content-Type: application/json');

//check
//error_reporting(E_ALL); ini_set('display_errors', 1);
error_reporting(E_ERROR | E_PARSE);


/*
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
*/

// php recuperaManzanas01.php --fr=0108
// localhost/test01.php?fr=0106
if ( isset( $_GET["fr"]) ) {
  $fr = htmlspecialchars($_GET["fr"]);
} else {
  $fr = getopt(null, ["fr:"]);
  $fr = $fr['fr'];
}

//pdcl
if ( isset( $_GET["pdcl"]) ) {
  $pdcl = htmlspecialchars($_GET["pdcl"]);
} else {
  $pdcl = getopt(null, ["pdcl:"]);
  $pdcl = $pdcl['pdcl'];
}


// Arrays
$arrayMZAOK = array();
$arrayMZAMIDJOIN = array();
$arrayMZAINNER = array();
$data =array();
$dataInner =array();






// topologia queries
function linestring_sql_rutas(
    $vari_mzaAct,
    $vari_nodosig,
    $vari_nodo1,
    $vari_nodo1geom,
    $vari_nodo2,
    $vari_nodo2geom,
    $vari_pdcl,
    $vari_fr
    )
  {

  global $linestring_sql;

  $linestring_sql = "

  with ruteo3 as (

  with ruteo2 as (

  SELECT DISTINCT
  ".$vari_mzaAct." as mzaid,
  g.id as edge,
  1 as path_id,
  ".$vari_nodosig." as path_seq,

  ".$vari_nodo1." as node,
  '".$vari_nodo1geom."' as nodogeom,

  ".$vari_nodo2." as nodo2id,
  '".$vari_nodo2geom."' as nodo2geom,
  g.tipo,
  g.nombre,
  case
  when  g.mzad like '%".$vari_mzaAct."' then g.desded
  else g.desdei
  end desde,

  case
  when  g.mzad like '%".$vari_mzaAct."' then g.hastad
  else g.hastai
  end hasta,

  (
    select hn from public.indec_geocoding_viviendas_indec geocode where ref_id = g.id
    order by
      st_distance (
          geocode.geom ,
          ( select distinct the_geom from public.indec_e0211linea_vertices_pgr where id = ".$vari_nodo1." limit 1) )
    limit 1
  ) as hn


  FROM
  public.indec_e0211linea g

  join
  ( select * FROM indec_e0211linea_vertices_pgr where id in (".$vari_nodo1.", ".$vari_nodo2.") ) v
  on

  ( g.source = ".$vari_nodo1." and g.target = ".$vari_nodo2." ) or
  ( g.source = ".$vari_nodo2." and g.target = ".$vari_nodo1." )


  )






  	select


  	(select st_astext(ST_CollectionHomogenize(geom)) from public.indec_e0211linea l where l.id = edge),
  	(
  		select ST_AsText(ST_CollectionHomogenize(ST_Boundary(ST_Union(geom)))) boundary_geom_astext FROM indec_e0211poligono
  			where prov||depto||codloc||frac||radio in ('".$vari_pdcl.$vari_fr."')
  		)
  	,

    ST_IsEmpty(
  	ST_CollectionHomogenize(
  	st_intersection(
  	(
  	select ST_CollectionHomogenize(geom) from public.indec_e0211linea l where l.id = edge
  	),

  	(
  	select ST_CollectionHomogenize(ST_Boundary(ST_Union(geom))) FROM indec_e0211poligono
  	where prov||depto||codloc||frac||radio in ('".$vari_pdcl.$vari_fr."')
  	)
  	)
  	)
  	) = 'f'
  	and

  	ST_GeometryType(
          			st_intersection(
  		(
  		select ST_CollectionHomogenize(geom) from public.indec_e0211linea l where l.id = edge
  		),

  		(
  		select ST_CollectionHomogenize(ST_Boundary(ST_Union(geom))) FROM indec_e0211poligono
  			where prov||depto||codloc||frac||radio in ('".$vari_pdcl.$vari_fr."')
  		)        			)
          		) in ('ST_LineString', 'ST_MultiLineString', 'ST_GeometryCollection') boundaryradio_intersecta
  	,



  	edge as edge2,

  	*,

  	CASE when ABS(hn - desde) < ABS(hn - hasta) then desde else hasta end altura_start,
  	CASE when ABS(hn - desde) < ABS(hn - hasta) then 'DESDE' else 'HASTA'  end altura_orderby,
  	CASE when MOD (desde::integer, 2) = 0 then 'PAR' else 'IMPAR' end as paridad

  	from ruteo2


  )


  select



  ROW_NUMBER () OVER (PARTITION BY geoc.ref_id ORDER BY
  cnombre,
  case when altura_orderby = 'HASTA' then geoc.hn end desc,
  case when altura_orderby = 'DESDE' then geoc.hn end asc,
  h4,hp ,hd) seqid_por_segmentolinea,
  case when geoc.ref_id is null then ruteo3.edge2 else geoc.ref_id end as geocref_id,
  geoc.id as geocid,

  case
    when geoc.hn is not null then geoc.hn::varchar
    else ruteo3.desde||'-'||ruteo3.hasta::varchar
  end as geochn_numeropuerta,

  ruteo3.nombre as geoccnombre,
  geoc.h4 as geoch4_tipovivienda,
  geoc.hp as geochp_edificio_numeropiso,
  geoc.hd as geochd_edificio_numerodepto,
  case when geoc.geom is null then  ST_GeomFromText(ruteo3.st_astext) else geoc.geom end as geocgeom,
  case when geoc.geom is null then  ruteo3.st_astext else st_astext(ST_CollectionHomogenize(geoc.geom)) end as geocgeomtext,
  ruteo3.*

  from ruteo3
  left join public.indec_geocoding_viviendas_indec geoc on geoc.ref_id = ruteo3.edge
  and MOD (desde::integer, 2) = MOD (geoc.hn::integer, 2)

  order by

  cnombre,
  case when altura_orderby = 'HASTA' then geoc.hn end desc,
  case when altura_orderby = 'DESDE' then geoc.hn end asc,
  h4,hp ,hd
  ";


  }




function pgr_ruteo_sql_rutas(
    $vari_pdcl,
    $vari_fr,
    $vari_mzaAct,
    $vari_pgr_verticeid_old,
    $vari_pgr_vertix_res_pgr_vertix_id,
    $tiporuteo
    )
{

  //start
  // pgrouting pgr_ksp para 2 vertices diferentes de inicio : fin
  global $pgr_ruteo_sql;

  // cantidad de caminos a rutear : 2
  if ($tiporuteo == 2) {


    // pgr_ksp
  $pgr_ruteo_sql = "
    with ruteo3 as (
    with ruteo2 as (
    with ruteo as ( SELECT * FROM pgr_ksp (
    'SELECT
     id,
     source, target,
     st_length(geomline::geography, true)/100000 as cost,
     st_length(geomline::geography, true)/100000 as reverse_cost
     FROM
     public.indec_e0211linea
     WHERE
     (
       mzai like ''%".$vari_fr.'0'.$vari_mzaAct."'' or
       mzad like ''%".$vari_fr.'0'.$vari_mzaAct."'' )',
       ".$vari_pgr_verticeid_old.",
       ".$vari_pgr_vertix_res_pgr_vertix_id.",
       2,
       true
    ) where edge > 0

    )
    select
    ".$vari_mzaAct." as mzaid,
    linea.tipo,
    linea.nombre,
    case
        when linea.mzad like '%".$vari_mzaAct."' then linea.desded
        else linea.desdei
        end desde,

        case
        when linea.mzad like '%".$vari_mzaAct."' then linea.hastad
        else linea.hastai
        end hasta,

        (
        select hn from public.indec_geocoding_viviendas_indec geocode where ref_id = ruteo.edge
        order by st_distance ( geocode.geom , ( select distinct the_geom from public.indec_e0211linea_vertices_pgr where id = ruteo.node limit 1) ) limit 1
        ) as hn,


    ruteo.*
    from ruteo
    join public.indec_e0211linea linea on ruteo.edge = linea.id

    )



    select


    (select st_astext(ST_CollectionHomogenize(geom)) from public.indec_e0211linea l where l.id = edge),
    (
    select ST_AsText(ST_CollectionHomogenize(ST_Boundary(ST_Union(geom)))) boundary_geom_astext FROM indec_e0211poligono
    where prov||depto||codloc||frac||radio in (
    '".$vari_pdcl.$vari_fr."'
    )
    )
    ,

    ST_IsEmpty(
    ST_CollectionHomogenize(
    st_intersection(
    (
    select ST_CollectionHomogenize(geom) from public.indec_e0211linea l where l.id = edge
    ),

    (
    select ST_CollectionHomogenize(ST_Boundary(ST_Union(geom))) FROM indec_e0211poligono
    where prov||depto||codloc||frac||radio in ('".$vari_pdcl.$vari_fr."')
    )
    )
    )
    ) = 'f'
    and

    ST_GeometryType(
            st_intersection(
    (
    select ST_CollectionHomogenize(geom) from public.indec_e0211linea l where l.id = edge
    ),

    (
    select ST_CollectionHomogenize(ST_Boundary(ST_Union(geom))) FROM indec_e0211poligono
    where prov||depto||codloc||frac||radio in (
    '".$vari_pdcl.$vari_fr."'
    )
    )        			)
          ) in ('ST_LineString', 'ST_MultiLineString', 'ST_GeometryCollection') boundaryradio_intersecta
    ,



    edge as edge2,

    *,

    CASE when ABS(hn - desde) < ABS(hn - hasta) then desde else hasta end altura_start,
    CASE when ABS(hn - desde) < ABS(hn - hasta) then 'DESDE' else 'HASTA'  end altura_orderby,
    CASE when MOD (desde::integer, 2) = 0 then 'PAR' else 'IMPAR' end as paridad

    from ruteo2

    )


    select

    ROW_NUMBER () OVER (PARTITION BY geoc.ref_id ORDER BY seq,
    cnombre,
    case when altura_orderby = 'HASTA' then geoc.hn end desc,
    case when altura_orderby = 'DESDE' then geoc.hn end asc,
    h4,hp ,hd) seqid_por_segmentolinea,

    case when geoc.ref_id is null then ruteo3.edge2 else geoc.ref_id end as geocref_id,
    geoc.id as geocid,

    case
      when geoc.hn is not null then geoc.hn::varchar
      else ruteo3.desde||'-'||ruteo3.hasta::varchar
    end as geochn_numeropuerta,

    ruteo3.nombre as geoccnombre,
    geoc.h4 as geoch4_tipovivienda,
    geoc.hp as geochp_edificio_numeropiso,
    geoc.hd as geochd_edificio_numerodepto,
    case when geoc.geom is null then  ST_GeomFromText(ruteo3.st_astext) else geoc.geom end as geocgeom,
    case when geoc.geom is null then  ruteo3.st_astext else st_astext(ST_CollectionHomogenize(geoc.geom)) end as geocgeomtext,
    ruteo3.*

    from ruteo3
    left join public.indec_geocoding_viviendas_indec geoc on geoc.ref_id = ruteo3.edge
    and MOD (desde::integer, 2) = MOD (geoc.hn::integer, 2)

    order by
    seq,
    cnombre,
    case when altura_orderby = 'HASTA' then geoc.hn end desc,
    case when altura_orderby = 'DESDE' then geoc.hn end asc,
    h4,hp ,hd
  ";

  }


  // cantidad de caminos ruteo  : 1
  elseif ($tiporuteo == 1) {

      // pgr_TSP // pgr_dijkstraCostMatrix
      $pgr_ruteo_sql = "
      with ruteo as (
      SELECT * FROM pgr_TSP(
          $$
          SELECT * FROM pgr_dijkstraCostMatrix
          (
            '
            SELECT
              id,
              source, target,
              st_length(geomline::geography, true)/100000 as cost,
              st_length(geomline::geography, true)/100000 as reverse_cost
            FROM
            public.indec_e0211linea
            WHERE
            (
              mzai like ''%".$vari_fr."0%'' or
              mzad like ''%".$vari_fr."0%''
            )
            AND
            (
              mzad like ''%".$vari_mzaAct."'' or
              mzai like ''%".$vari_mzaAct."''
            )
            ',
            ( SELECT array_agg(id) FROM indec_e0211linea_vertices_pgr ),
            directed := false
          )
          $$,
          start_id := ".$vari_pgr_vertix_res_pgr_vertix_id.",
          randomize := false
        )
        )
        select
        *,
        ( select distinct st_astext(ST_CollectionHomogenize(the_geom)) from public.indec_e0211linea_vertices_pgr where id = ruteo.node limit 1) as node_geom
        from ruteo
        ";

  }

}
//end funcion ruteos







try {






    //localhost
    $mbd = new PDO('pgsql:host=localhost;dbname=gisdata', 'indec', 'indec');

    foreach($mbd->query( "
      with mzas as
      (

      WITH const (pdclfr,fr) as ( VALUES ('".$pdcl.$fr."','".$fr."') )

    	SELECT
    		pdclfr,
        fr,

    		pol.mzatxt mzaid,

    		ST_GeometryType(
    		st_intersection(
    		pol.geom,
    		(select ST_Boundary(st_union(st_setsrid(geom,4326))) from indec_e0211poligono where prov||depto||codloc||frac||radio = pdclfr)
    		)
    		) in ('ST_LineString', 'ST_MultiLineString', 'ST_GeometryCollection')
    		as tipo,

    		(
    		select
    		array_to_json(array_agg(polNext.mzatxt))
    		from public.indec_e0211poligono as polNext
    			where
    			prov||depto||codloc||frac||radio = pdclfr and
    			st_intersects(pol.geom,polNext.geom)
    			and ST_GeometryType(st_intersection(pol.geom,polNext.geom)) in ('ST_LineString', 'ST_MultiLineString')
    		) as mza_touches,

    		(
    		select
    		array_to_json(array_agg(ST_Distance(st_centroid(pol.geom),ST_Centroid(polNext.geom))))
    		from public.indec_e0211poligono as polNext
    			where
    			prov||depto||codloc||frac||radio = pdclfr and
    			st_touches(pol.geom,polNext.geom)
    			and ST_GeometryType(st_intersection(pol.geom,polNext.geom)) in ('ST_LineString', 'ST_MultiLineString')
    		) as mza_touchescentdist


    		,
    		ST_GeometryType(
    			st_intersection(
    			pol.geom,
    			(select ST_Boundary(st_union(st_setsrid(geom,4326))) from indec_e0211poligono where prov||depto||codloc||frac||radio = pdclfr)
    			)
    		) geotype,

    		st_astext(ST_CollectionExtract(
    		st_intersection(
    		pol.geom,
    		(select ST_Boundary(st_union(st_setsrid(geom,4326))) from indec_e0211poligono where prov||depto||codloc||frac||radio = pdclfr)
    		),1)) colex



    	FROM
    		const,
    		public.indec_e0211poligono as pol
    	WHERE


    		prov||depto||codloc||frac||radio = pdclfr and

    		ST_GeometryType(
    			st_intersection(
    			pol.geom,
    			(select ST_Boundary(st_union(st_setsrid(geom,4326))) from indec_e0211poligono where prov||depto||codloc||frac||radio = pdclfr)
    			)
    		) in ('ST_LineString', 'ST_MultiLineString', 'ST_GeometryCollection')

    )

    select mzaid, tipo, mza_touches ,mza_touchescentdist , geotype
    from mzas
    where colex in ('POINT EMPTY','MULTIPOINT EMPTY')
    ORDER BY length(mza_touches::text)"


    ) as $fila) {

  	//data : manzana id y manzanas intersectadas
  	array_push($data, array('mzaid'=>json_decode($fila['mzaid']) , 'touches' => json_decode($fila['mza_touches'])));

    }



for($i = 0; $i < count($data); ++$i) {


  //manzanas que tocan
	foreach ( $data[$i]['touches'] as $valtouch) {

		if (empty($filacandidata)) {

	  array_push($arrayMZAOK,$data[$i]['mzaid']);

		foreach($mbd->query("
		SELECT
		mzatxt
		FROM public.indec_e0211poligono
		WHERE
    prov||depto||codloc||frac||radio = '".$pdcl.$fr."' and
		st_intersects(
		geom,
			(
			SELECT
			ST_CollectionExtract(
				st_intersection(
				geom, (select ST_Boundary(st_union(st_setsrid(geom,4326))) from indec_e0211poligono where prov||depto||codloc||frac||radio = '".$pdcl.$fr."')
				)
			,2)

			FROM public.indec_e0211poligono
			WHERE prov||depto||codloc||frac||radio = '".$pdcl.$fr."' and mzatxt = '".$valtouch."'
			)
		)
		and mzatxt not in (".implode(",", $arrayMZAOK).",".$valtouch.") limit 1" ) as $filaposible) {

			if (empty($filaposible)) {} else {

				$filacandidata = $filaposible['mzatxt'];

				if (!in_array($valtouch, $arrayMZAOK)) {
					array_push($arrayMZAOK,$valtouch);
				}
				if (!in_array($filaposible['mzatxt'], $arrayMZAOK)) {
					array_push($arrayMZAOK,$filacandidata);
				}

				$arrayMZAOK = array_unique($arrayMZAOK);

			}

		}

} else {

		foreach(
    $mbd->query("
		SELECT
		mzatxt
		FROM public.indec_e0211poligono
		WHERE prov||depto||codloc||frac||radio = '".$pdcl.$fr."' and
		st_intersects(
		geom,
			(
			SELECT
			ST_CollectionExtract(
				st_intersection(
				geom, (select ST_Boundary(st_union(st_setsrid(geom,4326))) from indec_e0211poligono where prov||depto||codloc||frac||radio = '".$pdcl.$fr."' )
				)
			,2)

			FROM public.indec_e0211poligono
			WHERE prov||depto||codloc||frac||radio = '".$pdcl.$fr."' and mzatxt = '".$filacandidata."'
			)
		)
		and mzatxt not in (".implode(",", $arrayMZAOK).",".$filacandidata.")"
    ) as $filaposible) {

			if (empty($filaposible)) {} else {
				$filacandidata = $filaposible['mzatxt'];

				if (!in_array($filaposible['mzatxt'], $arrayMZAOK)) {
					array_push($arrayMZAOK,$filacandidata);
				}
				$arrayMZAOK = array_unique($arrayMZAOK);
			}

		}
} //else




} //touches
} //for

$arrayMZAOK = array_unique($arrayMZAOK);



















/*
Generar un subradio de manzanas no boundaries repitiendo el proceso excluyendo las manzanas boundaries.
Teniendo como secuencial la ultima del array boundaries y la primera adyacente de las manzanas no boundaries.
*/

foreach($mbd->query( "

    with mzasinner as
    (
    WITH const (pdclfr,fr) as (
       values ('".$pdcl.$fr."','".$fr."')
    )

  	SELECT
    pdclfr,
    fr,

		pol.mzatxt mzaid,

		ST_GeometryType(
		st_intersection(
		pol.geom,
		(select ST_Boundary(st_union(st_setsrid(geom,4326)))
		 from indec_e0211poligono
		 where prov||depto||codloc||frac||radio = pdclfr
		 and mzatxt not in (".implode(",", $arrayMZAOK).") )
		)
		) in ('ST_LineString', 'ST_MultiLineString', 'ST_GeometryCollection')
		and mzatxt not in (".implode(",", $arrayMZAOK).")
		as tipo,

		(

		select
		array_to_json(array_agg(polNext.mzatxt))
		from public.indec_e0211poligono as polNext
			where
			prov||depto||codloc||frac||radio = pdclfr and
			st_intersects(pol.geom,polNext.geom)
			and ST_GeometryType(st_intersection(pol.geom,polNext.geom)) in ('ST_LineString', 'ST_MultiLineString')
			and mzatxt not in (".implode(",", $arrayMZAOK).")

		) as mza_touches,

		(
		select
		array_to_json(array_agg(ST_Distance(st_centroid(pol.geom),ST_Centroid(polNext.geom))))
		from public.indec_e0211poligono as polNext
			where
			prov||depto||codloc||frac||radio = pdclfr and
			st_touches(pol.geom,polNext.geom)
			and ST_GeometryType(st_intersection(pol.geom,polNext.geom)) in ('ST_LineString', 'ST_MultiLineString')
			and mzatxt not in (".implode(",", $arrayMZAOK).")
		) as mza_touchescentdist

		,
		ST_GeometryType(
			st_intersection(
			pol.geom,
			(select ST_Boundary(st_union(st_setsrid(geom,4326)))
			 from indec_e0211poligono
			 where prov||depto||codloc||frac||radio = pdclfr and
			 mzatxt not in (".implode(",", $arrayMZAOK).") )
			)
		) geotype

	FROM
		const,
		public.indec_e0211poligono as pol
	WHERE


		prov||depto||codloc||frac||radio = pdclfr and

		ST_GeometryType(
			st_intersection(
			pol.geom,
			(select ST_Boundary(st_union(st_setsrid(geom,4326)))
			 from indec_e0211poligono
			 where prov||depto||codloc||frac||radio = pdclfr and
			 mzatxt not in (".implode(",", $arrayMZAOK).") )
			)
		) in ('ST_LineString', 'ST_MultiLineString', 'ST_GeometryCollection')
		and mzatxt not in (".implode(",", $arrayMZAOK).")
)

select mzaid, tipo,  mza_touches ,mza_touchescentdist , geotype
from mzasinner
where mzaid not in (".implode(",", $arrayMZAOK).")
ORDER BY length(mza_touches::text)"


 ) as $fila) {

	array_push($dataInner, array('mzaid'=>json_decode($fila['mzaid']), 'touches' => json_decode($fila['mza_touches'])));

}








for($i = 0; $i < count($dataInner); ++$i) {

	foreach ( $dataInner[$i]['touches'] as $valtouchInner) {

		if (empty($filacandidataInner)) {

	    array_push($arrayMZAINNER,$dataInner[$i]['mzaid']);

      foreach($mbd->query("
      SELECT
      mzatxt
      FROM public.indec_e0211poligono
      WHERE prov||depto||codloc||frac||radio = '".$pdcl.$fr."' and
      st_intersects(
      geom,
      	(
      	SELECT
      	ST_CollectionExtract(
      		st_intersection(
      		geom, (select ST_Boundary(st_union(st_setsrid(geom,4326))) from indec_e0211poligono where prov||depto||codloc||frac||radio = '".$pdcl.$fr."'
      		and mzatxt not in (".implode(",", $arrayMZAOK).") )

      		)
      	,2)

      	FROM public.indec_e0211poligono
      	WHERE prov||depto||codloc||frac||radio = '".$pdcl.$fr."' and mzatxt = '".$valtouchInner."'
      	and mzatxt not in (".implode(",", $arrayMZAOK).")
      	)
      )
      and mzatxt not in (".implode(",", $arrayMZAOK).",".implode(",", $arrayMZAINNER).",".$valtouchInner.")" ) as $filaposibleInner) {

			if (empty($filaposibleInner)) {} else {
				$filacandidataInner = $filaposibleInner['mzatxt'];

				if (!in_array($valtouchInner, $arrayMZAINNER)) {
					array_push($arrayMZAINNER,$valtouchInner);
				}
				if (!in_array($filaposibleInner['mzatxt'], $arrayMZAINNER)) {
					array_push($arrayMZAINNER,$filacandidataInner);
				}

				$arrayMZAINNER = array_unique($arrayMZAINNER);

			}



		}

} else {


		foreach($mbd->query("
		SELECT
		mzatxt
		FROM public.indec_e0211poligono
		WHERE prov||depto||codloc||frac||radio = '".$pdcl.$fr."' and
		st_intersects(
		geom,
			(
			SELECT
			ST_CollectionExtract(
				st_intersection(
				geom, (select ST_Boundary(st_union(st_setsrid(geom,4326))) from indec_e0211poligono where prov||depto||codloc||frac||radio = '".$pdcl.$fr."'
				and mzatxt not in (".implode(",", $arrayMZAOK).") )

				)
			,2)

			FROM public.indec_e0211poligono
			WHERE prov||depto||codloc||frac||radio = '".$pdcl.$fr."' and mzatxt = '".$filacandidataInner."'
			and mzatxt not in (".implode(",", $arrayMZAOK).")
			)
		)
		and mzatxt not in (".implode(",", $arrayMZAOK).",".implode(",", $arrayMZAINNER).",".$filacandidataInner.")" ) as $filaposibleInner) {







			if (empty($filaposibleInner)) {} else {
				$filacandidataInner = $filaposibleInner['mzatxt'];



				if (!in_array($filaposibleInner['mzatxt'], $arrayMZAINNER)) {
					array_push($arrayMZAINNER,$filacandidataInner);
				}



				$arrayMZAINNER = array_unique($arrayMZAINNER);


			}



		}
}




	}
}

	$arrayMZAINNER = array_unique($arrayMZAINNER);
	array_push($arrayMZAMIDJOIN, end($arrayMZAOK));
	array_push($arrayMZAMIDJOIN, $arrayMZAINNER[0]);































if (!empty($arrayMZAOK) ) {

$arrayMZAOKLine = "ST_AsText(ST_MakeLine( ST_GeomFromText('MULTIPOINT(";

  foreach ($arrayMZAOK as $mzaid) {

  	foreach($mbd->query( "
  	SELECT st_X(ST_Centroid(geom)) x, st_Y(ST_Centroid(geom)) y, st_astext(ST_Centroid(geom)) centro  FROM public.indec_e0211poligono WHERE prov||depto||codloc||frac||radio = '".$pdcl.$fr."' and mzatxt = ".$mzaid. " limit 1"
  	) as $fila) {

  		$arrayMZAOKLine .= $fila['x'].' ';
  		$arrayMZAOKLine .= $fila['y'].", ";

  	}


  }

$arrayMZAOKLine = rtrim($arrayMZAOKLine, ', ');


}
















if (!empty($arrayMZAMIDJOIN) ) {


$arrayMZAMIDJOINLine = "ST_AsText(ST_MakeLine( ST_GeomFromText('MULTIPOINT(";
foreach ($arrayMZAMIDJOIN as $mzaid) {
	foreach($mbd->query( "
	SELECT st_X(ST_Centroid(geom)) x, st_Y(ST_Centroid(geom)) y, st_astext(ST_Centroid(geom)) centro  FROM public.indec_e0211poligono WHERE prov||depto||codloc||frac||radio = '".$pdcl.$fr."' and mzatxt = ".$mzaid. " limit 1"
	) as $fila) {
		$arrayMZAMIDJOINLine .= $fila['x'].' ';
		$arrayMZAMIDJOINLine .= $fila['y'].", ";
	}
}
$arrayMZAMIDJOINLine = rtrim($arrayMZAMIDJOINLine, ', ');


}





if (!empty($arrayMZAINNER) ) {

$arrayMZAINNERLine = "ST_AsText(ST_MakeLine( ST_GeomFromText('MULTIPOINT(";
foreach ($arrayMZAINNER as $mzaid) {
	foreach($mbd->query( "
	SELECT st_X(ST_Centroid(geom)) x, st_Y(ST_Centroid(geom)) y, st_astext(ST_Centroid(geom)) centro  FROM public.indec_e0211poligono WHERE prov||depto||codloc||frac||radio = '".$pdcl.$fr."' and mzatxt = ".$mzaid. " limit 1"
	) as $fila) {
		$arrayMZAINNERLine .= $fila['x'].' ';
		$arrayMZAINNERLine .= $fila['y'].", ";
	}
}
$arrayMZAINNERLine = rtrim($arrayMZAINNERLine, ', ');

}













//start

$respuestamza = [];



for ($x = 0; $x < count($arrayMZAOK); $x++) {

    $y = $x+1;
    $z = $y+1;

	// manzana actual (1), manzana siguiente (2), manzana tercera (3)
	$mzaAct  = $arrayMZAOK[$x];
	$mzaSig  = $arrayMZAOK[$y];
  $mzaTerc = $arrayMZAOK[$z];



  //if $mzaTipo:
    //inicial : 1 ruteo desde-hasta : mismo vertice de interseccion adyacente.
    //medio   : 2 ruteos desde adyacencia inicial a adyacencia siguiente
    //final   : 1 ruteo desde-hasta : mismo vertice de interseccion adyacente.

  if ( $x+1 == count($arrayMZAOK) ) {
    $mzaCantRutas = 1;
    $mzaTipoPath = 'end';
  } elseif ( $x+1 == 1 ) {
    $mzaCantRutas = 1;
    $mzaTipoPath = 'start';
  } else {
    $mzaCantRutas = 2;
    $mzaTipoPath ='mid';
  }


  //posee 3ra manz
  if ( !empty($mzaTerc ) ) {

    			foreach($mbd->query(
          "
          WITH orderpunto as (

          WITH linderasmza as (

          SELECT
          distinct

          st_astext(
            ST_LineMerge(
              st_intersection(
              (select distinct geom from public.indec_e0211poligono where prov||depto||codloc||frac||radio = '".$pdcl.$fr."'  and mzatxt =  '".$mzaAct."'  ),
              (select distinct geom from public.indec_e0211poligono where prov||depto||codloc||frac||radio = '".$pdcl.$fr."'  and mzatxt =  '".$mzaSig."'  )
              )
           )
          ) intersect_lineamza,

          st_astext(

            ST_CollectionHomogenize(
            st_intersection(
            (
            st_intersection(
            (select distinct geom from public.indec_e0211poligono where prov||depto||codloc||frac||radio = '".$pdcl.$fr."'  and mzatxt =  '".$mzaAct."'  ),
            (select distinct geom from public.indec_e0211poligono where prov||depto||codloc||frac||radio = '".$pdcl.$fr."'  and mzatxt =  '".$mzaSig."'  )
            )
            ),
            (
            select ST_Boundary(st_union(geom)) from indec_e0211poligono where prov||depto||codloc||frac||radio = '".$pdcl.$fr."'
            )
           )
         )
          ) intersect_lineabound

          FROM  public.indec_e0211poligono
          WHERE prov||depto||codloc||frac||radio = '".$pdcl.$fr."'
          limit 1

          )


          SELECT

            intersect_lineamza,
            intersect_lineabound,

            st_area(
              st_intersection(
                ST_SetSRID(ST_Buffer((dp).geom ,10),4326),
                ( select distinct st_union(geom) from public.indec_e0211poligono where prov||depto||codloc||frac||radio = '".$pdcl.$fr."' )
              )
            ) areaorder,

            (dp).path[1] As index,

            ST_AsText((dp).geom) As wktnode,


            (dp).geom <#>

            ST_CollectionHomogenize(
            st_intersection(
            (
            st_intersection(
            (select distinct geom from public.indec_e0211poligono where prov||depto||codloc||frac||radio = '".$pdcl.$fr."'  and mzatxt =  '".$mzaSig."'  ),
            (select distinct geom from public.indec_e0211poligono where prov||depto||codloc||frac||radio = '".$pdcl.$fr."'  and mzatxt =  '".$mzaTerc."'  )
            )
            ),
            (
            select ST_Boundary(st_union(geom)) from indec_e0211poligono where prov||depto||codloc||frac||radio = '".$pdcl.$fr."'
            )
            )

          ) dist

          FROM (
                SELECT
                    ST_DumpPoints(intersect_lineabound) AS dp,
                    intersect_lineamza,
                    intersect_lineabound
                from linderasmza
                ) As foo

          )

          select * from orderpunto
          order by areaorder asc, dist asc
          limit 1
    		"

    		 ) as $fila) {


          // punto de interseccion de manzana act y sig.
					$wkt = $fila['wktnode'];



          $pgr_verticeid_old = '';
          if (isset($pgr_vertix_res['pgr_vertix_id'])) {
          $pgr_verticeid_old = $pgr_vertix_res['pgr_vertix_id'];
          }
					// id del vertice segun la geometria del punto de adyacencia entre manzanas
					$pgr_vertix_sql = "
						select id as pgr_vertix_id
						from public.indec_e0211linea_vertices_pgr
						where st_astext(the_geom) = '".$wkt."'
						limit 1
					";
					$pgr_vertix = $mbd->prepare($pgr_vertix_sql);
					$pgr_vertix->execute();
					$pgr_vertix_res = $pgr_vertix->fetch(PDO::FETCH_ASSOC);



$linestring_ruteo_res = array();
$linestring_ruteo_res_order_true = array();
$linestring_ruteo_res_order_false = array();



if ( $mzaCantRutas == 2) {


  pgr_ruteo_sql_rutas( $pdcl, $fr, $mzaAct, $pgr_verticeid_old, $pgr_vertix_res['pgr_vertix_id'], 2 );
  $linestring_ruteo = $mbd->prepare($pgr_ruteo_sql);
  $linestring_ruteo->execute();


  while ($fila = $linestring_ruteo->fetch(PDO::FETCH_ASSOC)) {

    if ($fila['boundaryradio_intersecta'] == true) {
      array_push(
        $linestring_ruteo_res_order_true,
        $fila
      );
    }

    else {
      array_push(
        $linestring_ruteo_res_order_false,
        $fila
      );
    }


  }


  $pgr_ruteo = $mbd->prepare($pgr_ruteo_sql);
  $pgr_ruteo->execute();
  $linestring_ruteo_res = $pgr_ruteo->fetchAll(PDO::FETCH_ASSOC);

} elseif ( $mzaCantRutas == 1) {


  pgr_ruteo_sql_rutas( $pdcl, $fr, $mzaAct, $pgr_verticeid_old, $pgr_vertix_res['pgr_vertix_id'], 1 );
  $pgr_ruteo = $mbd->prepare($pgr_ruteo_sql);
  $pgr_ruteo->execute();
  $pgr_ruteo_res1 = $pgr_ruteo->fetchAll(PDO::FETCH_ASSOC);


    for ($nodo = 0; $nodo <= count($pgr_ruteo_res1)-2; $nodo++) {



      $nodosig = $nodo+1;

      $nodo1 = $pgr_ruteo_res1[$nodo]['node'];
      $nodo1geom = $pgr_ruteo_res1[$nodo]['node_geom'];

      $nodo2 = $pgr_ruteo_res1[$nodosig]['node'];
      $nodo2geom = $pgr_ruteo_res1[$nodosig]['node_geom'];

      linestring_sql_rutas( $mzaAct, $nodosig, $nodo1, $nodo1geom, $nodo2, $nodo2geom, $pdcl, $fr );



      $linestring_ruteo = $mbd->prepare($linestring_sql);
      $linestring_ruteo->execute();

      while ($fila = $linestring_ruteo->fetch(PDO::FETCH_ASSOC)) {


        if ($fila['boundaryradio_intersecta'] == 'true') {
          array_push(
            $linestring_ruteo_res_order_true,
            $fila
          );
        }

        else {
          array_push(
            $linestring_ruteo_res_order_false,
            $fila
          );
        }

      }


      $linestring_ruteo = $mbd->prepare($linestring_sql);
      $linestring_ruteo->execute();
      array_push($linestring_ruteo_res, $linestring_ruteo->fetchAll(PDO::FETCH_ASSOC));



  }

}


if ( count($linestring_ruteo_res[0][0]) > 0 ) {

  $linestring_ruteo_res2 = array();
  foreach ($linestring_ruteo_res as $pgrval) {
    foreach ($pgrval as $pgrval2) {
      array_push($linestring_ruteo_res2,$pgrval2);
    }
  }

  // Resultados de la ultima manzana (fin de la secuencia)
  $respuestamza[$x] = [
    'radio' => $fr,
    'manzana_cant'=> count($arrayMZAOK),
    'manzana_cantrutas'=> $mzaCantRutas,
    'mzaTipoPath'=> $mzaTipoPath,
    'manzana_pos' => $x+1,
    'manzana_act'=>$mzaAct,
    'manzana_sig'=> null,
    'entremanzanas_linea' => null,
    'entremanzanas_punto_interseccion' => null,
    'pgr_verticeid_old'=> $pgr_vertix_res['pgr_vertix_id'],
    'pgr_verticeid' => '',
    'esmultiarray1' => ( count($linestring_ruteo_res[0][0] )),
    'pgr_ruteo_res' => $linestring_ruteo_res2,
    'linestring_ruteo_res_order_true'=>$linestring_ruteo_res_order_true,
    'linestring_ruteo_res_order_false'=>$linestring_ruteo_res_order_false
  ];

} else {

  // Resultados por radio
  $respuestamza[$x] = [
    'radio' => $fr,
    'manzana_cant'=> count($arrayMZAOK),
    'manzana_cantrutas'=> $mzaCantRutas,
    'mzaTipoPath'=> $mzaTipoPath,
    'manzana_pos' => $x+1,
    'manzana_act'=>$mzaAct,
    'manzana_sig' => $mzaSig,
    'entremanzanas_linea' => $fila['intersect_lineamza'],
    'entremanzanas_punto_interseccion' => $wkt,
    'pgr_verticeid_old'=>$pgr_verticeid_old,
    'pgr_verticeid' => $pgr_vertix_res['pgr_vertix_id'],
    'esmultiarray1' => ( count($linestring_ruteo_res[0][0] )),
    'pgr_ruteo_res' => $linestring_ruteo_res,
    'linestring_ruteo_res_order_true'=>$linestring_ruteo_res_order_true,
    'linestring_ruteo_res_order_false'=>$linestring_ruteo_res_order_false
  ];

}







    		}

  } //end 3ramanz

  // si POSEE mzasig
  elseif ( !empty($mzaSig   ) ) {


    $linestring_ruteo_res = array();
    $linestring_ruteo_res_order_true = array();
    $linestring_ruteo_res_order_false = array();

		foreach($mbd->query(
    "
		SELECT
		distinct

		st_astext(
      ST_LineMerge(
    		st_intersection(
    		(select distinct geom from public.indec_e0211poligono where prov||depto||codloc||frac||radio in ('".$pdcl.$fr."')  and mzatxt =  '".$mzaAct."'  ),
    		(select distinct geom from public.indec_e0211poligono where prov||depto||codloc||frac||radio in ('".$pdcl.$fr."')  and mzatxt =  '".$mzaSig."'  )
		    )
     )
		) intersect_lineamza,

		st_astext(
      st_geometryn(
      ST_CollectionHomogenize(
        st_intersection(
			(
			st_intersection(
			(select distinct geom from public.indec_e0211poligono where prov||depto||codloc||frac||radio in ('".$pdcl.$fr."')  and mzatxt =  '".$mzaAct."'  ),
			(select distinct geom from public.indec_e0211poligono where prov||depto||codloc||frac||radio in ('".$pdcl.$fr."')  and mzatxt =  '".$mzaSig."'  )
			)
			),
			(
			select ST_Boundary(st_union(geom)) from indec_e0211poligono where prov||depto||codloc||frac||radio in ('".$pdcl.$fr."')
			)
		 )
   ),1)
		) intersect_lineabound

		FROM  public.indec_e0211poligono
		WHERE prov||depto||codloc||frac||radio in ('".$pdcl.$fr."')
		limit 1
		"


		 ) as $fila) {



		$wkt = $fila['intersect_lineabound'];

    $pgr_verticeid_old = '';
    if (isset($pgr_vertix_res['pgr_vertix_id'])) {
      $pgr_verticeid_old = $pgr_vertix_res['pgr_vertix_id'];
    }
		// id del vertice segun la geometria del punto de adyacencia entre manzanas
		$pgr_vertix_sql = "
			select id as pgr_vertix_id
			from public.indec_e0211linea_vertices_pgr
			where st_astext(the_geom) = '".$wkt."'
			limit 1
		";
		$pgr_vertix = $mbd->prepare($pgr_vertix_sql);
		$pgr_vertix->execute();
		$pgr_vertix_res = $pgr_vertix->fetch(PDO::FETCH_ASSOC);


    if ( $mzaCantRutas == 2) {

      pgr_ruteo_sql_rutas( $pdcl, $fr, $mzaAct, $pgr_verticeid_old, $pgr_vertix_res['pgr_vertix_id'], 2 );
      $linestring_ruteo = $mbd->prepare($pgr_ruteo_sql);
      $linestring_ruteo->execute();

      while ($fila = $linestring_ruteo->fetch(PDO::FETCH_ASSOC)) {

        if ($fila['boundaryradio_intersecta'] == true) {
          array_push(
            $linestring_ruteo_res_order_true,
            $fila
          );
        }

        else {
          array_push(
            $linestring_ruteo_res_order_false,
            $fila
          );
        }

      }

      $pgr_ruteo = $mbd->prepare($pgr_ruteo_sql);
      $pgr_ruteo->execute();
      $linestring_ruteo_res = $pgr_ruteo->fetchAll(PDO::FETCH_ASSOC);

    } elseif ( $mzaCantRutas == 1) {

    }

		  // Resultados por manzana middle
		  $respuestamza[$x] = [
		    'radio' => $fr,
        'manzana_cant'=> count($arrayMZAOK),
        'manzana_cantrutas'=> $mzaCantRutas,
        'mzaTipoPath'=> $mzaTipoPath,
        'manzana_pos' => $x+1,
		    'manzana_act'=>$mzaAct,
		    'manzana_sig' => $mzaSig,
		    'entremanzanas_linea' => $fila['intersect_lineamza'],
		    'entremanzanas_punto_interseccion' => $wkt,
			  'pgr_verticeid_old'=>$pgr_verticeid_old,
        'pgr_verticeid' => $pgr_vertix_res['pgr_vertix_id'],
        'esmultiarray1' => ( count($linestring_ruteo_res[0][0] )),
        'pgr_ruteo_res' => $linestring_ruteo_res,
        'linestring_ruteo_res_order_true'=>$linestring_ruteo_res_order_true,
        'linestring_ruteo_res_order_false'=>$linestring_ruteo_res_order_false

		  ];



		}





	} //mza 2

else {


    $linestring_ruteo_res = array();
    $linestring_ruteo_res_order_true = array();
    $linestring_ruteo_res_order_false = array();

    pgr_ruteo_sql_rutas( $pdcl, $fr, $mzaAct, $pgr_verticeid_old, $pgr_vertix_res['pgr_vertix_id'], 1 );
    $pgr_ruteo = $mbd->prepare($pgr_ruteo_sql);
    $pgr_ruteo->execute();
    $pgr_ruteo_res1 = $pgr_ruteo->fetchAll(PDO::FETCH_ASSOC);


    for ($nodo = 0; $nodo <= count($pgr_ruteo_res1)-2; $nodo++) {



        $nodosig = $nodo+1;

        $nodo1 = $pgr_ruteo_res1[$nodo]['node'];
        $nodo1geom = $pgr_ruteo_res1[$nodo]['node_geom'];

        $nodo2 = $pgr_ruteo_res1[$nodosig]['node'];
        $nodo2geom = $pgr_ruteo_res1[$nodosig]['node_geom'];

        linestring_sql_rutas( $mzaAct, $nodosig, $nodo1, $nodo1geom, $nodo2, $nodo2geom, $pdcl, $fr  );

        $linestring_ruteo = $mbd->prepare($linestring_sql);
        $linestring_ruteo->execute();

        while ($fila = $linestring_ruteo->fetch(PDO::FETCH_ASSOC)) {

          if ($fila['boundaryradio_intersecta'] == 'true') {
            array_push(
              $linestring_ruteo_res_order_true,
              $fila
            );
          }

          else {
            array_push(
              $linestring_ruteo_res_order_false,
              $fila
            );
          }

        }


        $linestring_ruteo = $mbd->prepare($linestring_sql);
        $linestring_ruteo->execute();
        array_push($linestring_ruteo_res, $linestring_ruteo->fetchAll(PDO::FETCH_ASSOC));


    }


if ( count($linestring_ruteo_res[0][0]) > 0 ) {


  $linestring_ruteo_res2 = array();
  foreach ($linestring_ruteo_res as $pgrval) {
    foreach ($pgrval as $pgrval2) {
      array_push($linestring_ruteo_res2,$pgrval2);
    }
  }

  // Resultados de la ultima manzana (fin de la secuencia)
  // Esta manzana es el inicio del subconjunto de manzanas no boundaries en caso de contener las mismas.
  $respuestamza[$x] = [

    'radio' => $fr,
    'manzana_cant'=> count($arrayMZAOK),
    'manzana_cantrutas'=> $mzaCantRutas,
    'mzaTipoPath'=> $mzaTipoPath,
    'manzana_pos' => $x+1,
    'manzana_act'=>$mzaAct,
    'manzana_sig'=> null,
    'entremanzanas_linea' => null,
    'entremanzanas_punto_interseccion' => null,
    //repite el ultimo vertice porque es de 1 ruteo (round route desde hacia mismo vertice)
    'pgr_verticeid_old'=> $pgr_vertix_res['pgr_vertix_id'],
    'pgr_verticeid' => '',
    'esmultiarray1' => ( count($linestring_ruteo_res[0][0] )),
    'pgr_ruteo_res' => $linestring_ruteo_res2,
    'linestring_ruteo_res_order_true'=>$linestring_ruteo_res_order_true,
    'linestring_ruteo_res_order_false'=>$linestring_ruteo_res_order_false

  ];

} else {

  // Resultados de la ultima manzana (fin de la secuencia)
  // Esta manzana es el inicio del subconjunto de manzanas no boundaries en caso de contener las mismas.
  $respuestamza[$x] = [

    'radio' => $fr,
    'manzana_cant'=> count($arrayMZAOK),
    'manzana_cantrutas'=> $mzaCantRutas,
    'mzaTipoPath'=> $mzaTipoPath,
    'manzana_pos' => $x+1,
    'manzana_act'=>$mzaAct,
    'manzana_sig'=> null,
    'entremanzanas_linea' => null,
    'entremanzanas_punto_interseccion' => null,
    //repite el ultimo vertice porque es de 1 ruteo (round route desde hacia mismo vertice)
    'pgr_verticeid_old'=> $pgr_vertix_res['pgr_vertix_id'],
    'pgr_verticeid' => '',
    'esmultiarray1' => ( count($linestring_ruteo_res[0][0] )),
    'pgr_ruteo_res' => $linestring_ruteo_res,
    'linestring_ruteo_res_order_true'=>$linestring_ruteo_res_order_true,
    'linestring_ruteo_res_order_false'=>$linestring_ruteo_res_order_false

  ];

}



}

} // mzasok



$mbd = null;

// datos del radio
$respuesta = [
'fraccionradio' => $fr,
'orden_mzas' =>$arrayMZAOK,
'orden_detalle' =>$respuestamza
];

//echo json_encode($respuesta);


$ordenruteo   = array();
$ordenruteoOK = array();

foreach ($respuesta as $valmza) {

  foreach ($valmza as $val) {


    if ($val['mzaTipoPath'] == 'start') {
      array_push(
          $ordenruteo,
          $val['pgr_ruteo_res']
        );
    }


    if ($val['mzaTipoPath'] == 'mid') {
        array_push(
            $ordenruteo,
            $val['linestring_ruteo_res_order_true']
          );
    }


    if ($val['mzaTipoPath'] == 'end') {
        array_push(
            $ordenruteo,
            $val['pgr_ruteo_res']
          );
    }


  }

}

//reverse inner boundary return
foreach ($respuesta as $valmza) {

  foreach (array_reverse($valmza) as $val) {

    if ($val['mzaTipoPath'] == 'mid') {
      array_push(
          $ordenruteo,
          array_reverse($val['linestring_ruteo_res_order_false'])
        );
    }

  }

}



for ($order0 = 0; $order0 < count($ordenruteo); $order0++) {

  for ($order1 = 0; $order1 < count($ordenruteo[$order0]); $order1++) {



        $datarow = [

        'order0' => $order0,
        'order1' => $order1,

        'seqid_por_segmentolinea' => $ordenruteo[$order0][$order1]['seqid_por_segmentolinea'],
        'geocref_id' => $ordenruteo[$order0][$order1]['geocref_id'],
        'geocid' => $ordenruteo[$order0][$order1]['geocid'],

        'geochn_numeropuerta' => $ordenruteo[$order0][$order1]['geochn_numeropuerta'],
        'geoccnombre' => $ordenruteo[$order0][$order1]['geoccnombre'],
        'geoch4_tipovivienda' => $ordenruteo[$order0][$order1]['geoch4_tipovivienda'],
        'geochp_edificio_numeropiso' => $ordenruteo[$order0][$order1]['geochp_edificio_numeropiso'],
        'geochd_edificio_numerodepto' => $ordenruteo[$order0][$order1]['geochd_edificio_numerodepto'],

        'geocgeomtext' => $ordenruteo[$order0][$order1]['geocgeomtext'],
        'st_astext' => $ordenruteo[$order0][$order1]['st_astext'],
        'boundary_geom_astext' => $ordenruteo[$order0][$order1]['boundary_geom_astext'],
        'boundaryradio_intersecta' => $ordenruteo[$order0][$order1]['boundaryradio_intersecta'],

        'edge2' => $ordenruteo[$order0][$order1]['edge2'],
        'mzaid' => $ordenruteo[$order0][$order1]['mzaid'],
        'path_id' => $ordenruteo[$order0][$order1]['path_id'],
        'path_seq' => $ordenruteo[$order0][$order1]['path_seq'],
        'tipo' => $ordenruteo[$order0][$order1]['tipo'],

        'nombre' => $ordenruteo[$order0][$order1]['nombre'],
        'desde' => $ordenruteo[$order0][$order1]['desde'],
        'hasta' => $ordenruteo[$order0][$order1]['hasta'],
        'hn' => $ordenruteo[$order0][$order1]['hn'],
        'node' => $ordenruteo[$order0][$order1]['node'],
        'altura_start' => $ordenruteo[$order0][$order1]['altura_start'],
        'altura_orderby' => $ordenruteo[$order0][$order1]['altura_orderby'],
        'paridad' => $ordenruteo[$order0][$order1]['paridad']

      ];


    array_push($ordenruteoOK, $datarow );




  }

}

// datos del radio
$respuestaOK = [
'fraccionradio' => $fr,
'orden_mzas' =>$arrayMZAOK,
'orden_detalle' =>$respuestamza,
'orden_ruteoOK' =>$ordenruteoOK
];

echo json_encode($respuestaOK);







      $mbd = new PDO('pgsql:host=localhost; dbname=gisdata', 'postgres', 'postgres');
      foreach ($ordenruteoOK as $ordenruteo) {

          $ruteosql =
                "INSERT INTO public.indec_res(
                order0, order1, seqid_por_segmentolinea, geocref_id, geocid,
                geochn_numeropuerta, geoccnombre, geoch4_tipovivienda, geochp_edificio_numeropiso,
                geochd_edificio_numerodepto, geocgeomtext, st_astext, boundary_geom_astext,
                boundaryradio_intersecta, edge2, mzaid, path_id, path_seq, tipo,
                nombre, desde, hasta, hn, node, altura_start, altura_orderby,
                paridad) VALUES (
                     ".$ordenruteo['order0'].",
                     ".$ordenruteo['order1'].",
                     ".$ordenruteo['seqid_por_segmentolinea'].",
                     ".$ordenruteo['geocref_id'].",
                     ".$ordenruteo['geocid'].",
                     ".$ordenruteo['geochn_numeropuerta'].",
                     '".$ordenruteo['geoccnombre']."',
                     ".$ordenruteo['geoch4_tipovivienda'].",
                     ".$ordenruteo['geochp_edificio_numeropiso'].",
                     ".$ordenruteo['geochd_edificio_numerodepto'].",
                     '".$ordenruteo['geocgeomtext']."',
                     '".$ordenruteo['st_astext']."',
                     '".$ordenruteo['boundary_geom_astext']."',
                     (case when '".$ordenruteo['boundaryradio_intersecta']."' = '1' then 1 else 0 end),
                     ".$ordenruteo['edge2'].",
                     ".$ordenruteo['mzaid'].",
                     ".$ordenruteo['path_id'].",
                     ".$ordenruteo['path_seq'].",
                     '".$ordenruteo['tipo']."',
                     '".$ordenruteo['nombre']."',
                     ".$ordenruteo['desde'].",
                     ".$ordenruteo['hasta'].",
                     ".$ordenruteo['hn'].",
                     ".$ordenruteo['node'].",
                     ".$ordenruteo['altura_start'].",
                     '".$ordenruteo['altura_orderby']."',
                     '".$ordenruteo['paridad']."'
                  )";

          $ruteoinserta = $mbd->prepare($ruteosql);
          $ruteoinsertaexecute = $ruteoinserta->execute();
      }


} catch (PDOException $e) {
    print "error consulta: " . $e->getMessage();
    die();
}

?>
