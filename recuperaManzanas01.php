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

// Arrays
$arrayMZAOK = array();
$arrayMZAMIDJOIN = array();
$arrayMZAINNER = array();
$data =array();
$dataInner =array();



try {

    //localhost
    $mbd = new PDO('pgsql:host=localhost;dbname=gisdata', 'indec', 'indec');

    foreach($mbd->query( "


with mzas as
(
	WITH const (fr) as (
	   values ('".$fr."')
	)

	SELECT
		fr,

		pol.mzatxt mzaid,

		ST_GeometryType(
		st_intersection(
		pol.geom,
		(select ST_Boundary(st_union(st_setsrid(geom,4326))) from indec_e0211poligono where frac||radio = fr)
		)
		) in ('ST_LineString', 'ST_MultiLineString', 'ST_GeometryCollection')
		as tipo,

		(
		select
		array_to_json(array_agg(polNext.mzatxt))
		from public.indec_e0211poligono as polNext
			where
			frac||radio = fr and
			st_intersects(pol.geom,polNext.geom)
			and ST_GeometryType(st_intersection(pol.geom,polNext.geom)) in ('ST_LineString', 'ST_MultiLineString')
		) as mza_touches,

		(
		select
		array_to_json(array_agg(ST_Distance(st_centroid(pol.geom),ST_Centroid(polNext.geom))))
		from public.indec_e0211poligono as polNext
			where
			frac||radio = fr and
			st_touches(pol.geom,polNext.geom)
			and ST_GeometryType(st_intersection(pol.geom,polNext.geom)) in ('ST_LineString', 'ST_MultiLineString')
		) as mza_touchescentdist


		,
		ST_GeometryType(
			st_intersection(
			pol.geom,
			(select ST_Boundary(st_union(st_setsrid(geom,4326))) from indec_e0211poligono where frac||radio = fr)
			)
		) geotype,


		st_astext(ST_CollectionExtract(
		st_intersection(
		pol.geom,
		(select ST_Boundary(st_union(st_setsrid(geom,4326))) from indec_e0211poligono where frac||radio = fr)
		),1)) colex



	FROM
		const,
		public.indec_e0211poligono as pol
	WHERE


		pol.frac||radio = fr and

		ST_GeometryType(
			st_intersection(
			pol.geom,
			(select ST_Boundary(st_union(st_setsrid(geom,4326))) from indec_e0211poligono where frac||radio = fr)
			)
		) in ('ST_LineString', 'ST_MultiLineString', 'ST_GeometryCollection')

)

select mzaid, tipo,  mza_touches ,mza_touchescentdist , geotype
from mzas
where colex in ('POINT EMPTY','MULTIPOINT EMPTY')
ORDER BY length(mza_touches::text)"


 ) as $fila) {

	//data : manzana id y manzanas intersectadas
	array_push($data, array('mzaid'=>json_decode($fila['mzaid']) , 'touches' => json_decode($fila['mza_touches'])));


}



for($i = 0; $i < count($data); ++$i) {



	foreach ( $data[$i]['touches'] as $valtouch) {






		if (empty($filacandidata)) {

	    array_push($arrayMZAOK,$data[$i]['mzaid']);





		foreach($mbd->query("
		SELECT
		mzatxt
		FROM public.indec_e0211poligono
		WHERE frac||radio = '".$fr."' and
		st_intersects(
		geom,
			(
			SELECT
			ST_CollectionExtract(
				st_intersection(
				geom, (select ST_Boundary(st_union(st_setsrid(geom,4326))) from indec_e0211poligono where frac||radio = '".$fr."')
				)
			,2)

			FROM public.indec_e0211poligono
			WHERE frac||radio = '".$fr."' and mzatxt = '".$valtouch."'
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

		foreach($mbd->query("
		SELECT
		mzatxt
		FROM public.indec_e0211poligono
		WHERE frac||radio = '".$fr."' and
		st_intersects(
		geom,
			(
			SELECT
			ST_CollectionExtract(
				st_intersection(
				geom, (select ST_Boundary(st_union(st_setsrid(geom,4326))) from indec_e0211poligono where frac||radio = '".$fr."')
				)
			,2)

			FROM public.indec_e0211poligono
			WHERE frac||radio = '".$fr."' and mzatxt = '".$filacandidata."'
			)
		)
		and mzatxt not in (".implode(",", $arrayMZAOK).",".$filacandidata.")" ) as $filaposible) {



			if (empty($filaposible)) {} else {
				$filacandidata = $filaposible['mzatxt'];



				if (!in_array($filaposible['mzatxt'], $arrayMZAOK)) {
					array_push($arrayMZAOK,$filacandidata);
				}



				$arrayMZAOK = array_unique($arrayMZAOK);


			}



		}
}




	}
}

$arrayMZAOK = array_unique($arrayMZAOK);



















/*
Generar un subradio de manzanas no boundaries repitiendo el proceso excluyendo las manzanas boundaries.
Teniendo como secuencial la ultima del array boundaries y la primera adyacente de las manzanas no boundaries.
*/




    foreach($mbd->query( "

with mzasinner as
(
	WITH const (fr) as (
	   values ('".$fr."')
	)

	SELECT
		fr,

		pol.mzatxt mzaid,

		ST_GeometryType(
		st_intersection(
		pol.geom,
		(select ST_Boundary(st_union(st_setsrid(geom,4326)))
		 from indec_e0211poligono
		 where frac||radio = fr
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
			frac||radio = fr and
			st_intersects(pol.geom,polNext.geom)
			and ST_GeometryType(st_intersection(pol.geom,polNext.geom)) in ('ST_LineString', 'ST_MultiLineString')
			and mzatxt not in (".implode(",", $arrayMZAOK).")

		) as mza_touches,

		(
		select
		array_to_json(array_agg(ST_Distance(st_centroid(pol.geom),ST_Centroid(polNext.geom))))
		from public.indec_e0211poligono as polNext
			where
			frac||radio = fr and
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
			 where frac||radio = fr and
			 mzatxt not in (".implode(",", $arrayMZAOK).") )
			)
		) geotype

	FROM
		const,
		public.indec_e0211poligono as pol
	WHERE


		pol.frac||radio = fr and

		ST_GeometryType(
			st_intersection(
			pol.geom,
			(select ST_Boundary(st_union(st_setsrid(geom,4326)))
			 from indec_e0211poligono
			 where frac||radio = fr and
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

	array_push($dataInner, array('mzaid'=>json_decode($fila['mzaid']) , 'touches' => json_decode($fila['mza_touches'])));

}








for($i = 0; $i < count($dataInner); ++$i) {




	foreach ( $dataInner[$i]['touches'] as $valtouchInner) {







		if (empty($filacandidataInner)) {
	    array_push($arrayMZAINNER,$dataInner[$i]['mzaid']);



		foreach($mbd->query("
		SELECT
		mzatxt
		FROM public.indec_e0211poligono
		WHERE frac||radio = '".$fr."' and
		st_intersects(
		geom,
			(
			SELECT
			ST_CollectionExtract(
				st_intersection(
				geom, (select ST_Boundary(st_union(st_setsrid(geom,4326))) from indec_e0211poligono where frac||radio = '".$fr."'
				and mzatxt not in (".implode(",", $arrayMZAOK).") )

				)
			,2)

			FROM public.indec_e0211poligono
			WHERE frac||radio = '".$fr."' and mzatxt = '".$valtouchInner."'
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
		WHERE frac||radio = '".$fr."' and
		st_intersects(
		geom,
			(
			SELECT
			ST_CollectionExtract(
				st_intersection(
				geom, (select ST_Boundary(st_union(st_setsrid(geom,4326))) from indec_e0211poligono where frac||radio = '".$fr."'
				and mzatxt not in (".implode(",", $arrayMZAOK).") )

				)
			,2)

			FROM public.indec_e0211poligono
			WHERE frac||radio = '".$fr."' and mzatxt = '".$filacandidataInner."'
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
  	SELECT st_X(ST_Centroid(geom)) x, st_Y(ST_Centroid(geom)) y, st_astext(ST_Centroid(geom)) centro  FROM public.indec_e0211poligono WHERE frac||radio = '".$fr."' and mzatxt = ".$mzaid. " limit 1"
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
	SELECT st_X(ST_Centroid(geom)) x, st_Y(ST_Centroid(geom)) y, st_astext(ST_Centroid(geom)) centro  FROM public.indec_e0211poligono WHERE frac||radio = '".$fr."' and mzatxt = ".$mzaid. " limit 1"
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
	SELECT st_X(ST_Centroid(geom)) x, st_Y(ST_Centroid(geom)) y, st_astext(ST_Centroid(geom)) centro  FROM public.indec_e0211poligono WHERE frac||radio = '".$fr."' and mzatxt = ".$mzaid. " limit 1"
	) as $fila) {
		$arrayMZAINNERLine .= $fila['x'].' ';
		$arrayMZAINNERLine .= $fila['y'].", ";
	}
}
$arrayMZAINNERLine = rtrim($arrayMZAINNERLine, ', ');





}















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
    $mzaTipo = 'fin';
  } elseif ( $x+1 == 1 ) {
    $mzaTipo = 'inicio';
  } else {
    $mzaTipo = 'medio';
  }


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
              (select distinct geom from public.indec_e0211poligono where frac||radio = '".$fr."'  and mzatxt =  '".$mzaAct."'  ),
              (select distinct geom from public.indec_e0211poligono where frac||radio = '".$fr."'  and mzatxt =  '".$mzaSig."'  )
              )
           )
          ) intersect_lineamza,

          st_astext(

            ST_CollectionHomogenize(
            st_intersection(
            (
            st_intersection(
            (select distinct geom from public.indec_e0211poligono where frac||radio = '".$fr."'  and mzatxt =  '".$mzaAct."'  ),
            (select distinct geom from public.indec_e0211poligono where frac||radio = '".$fr."'  and mzatxt =  '".$mzaSig."'  )
            )
            ),
            (
            select ST_Boundary(st_union(geom)) from indec_e0211poligono where frac||radio = '".$fr."'
            )
           )
         )
          ) intersect_lineabound

          FROM  public.indec_e0211poligono
          WHERE frac||radio = '".$fr."'
          limit 1

          )


          SELECT

            intersect_lineamza,
            intersect_lineabound,

            st_area(
              st_intersection(
                ST_SetSRID(ST_Buffer((dp).geom ,10),4326),
                ( select distinct st_union(geom) from public.indec_e0211poligono where frac||radio = '".$fr."' )
              )
            ) areaorder,

            (dp).path[1] As index,

            ST_AsText((dp).geom) As wktnode,


            (dp).geom <#>

            ST_CollectionHomogenize(
            st_intersection(
            (
            st_intersection(
            (select distinct geom from public.indec_e0211poligono where frac||radio = '".$fr."'  and mzatxt =  '".$mzaSig."'  ),
            (select distinct geom from public.indec_e0211poligono where frac||radio = '".$fr."'  and mzatxt =  '".$mzaTerc."'  )
            )
            ),
            (
            select ST_Boundary(st_union(geom)) from indec_e0211poligono where frac||radio = '".$fr."'
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



/*
					$puntoidsql = "
					with dumpmza as (
					select
					(ST_DumpPoints(geom)).*
					from public.indec_e0211poligono where frac||radio = '".$fr."'  and mzatxt =  '".$mzaAct."'
					)
					 select
					 path[3]::integer as puntoid
					 from dumpmza
					 where st_astext(geom) = '".$wkt."'
					 limit 1
					";
					$puntoid = $mbd->prepare($puntoidsql);
					$puntoid->execute();
					$puntoid_res = $puntoid->fetch(PDO::FETCH_ASSOC);

					$puntosigidsql = "
					with dumpmza as (
					select
					(ST_DumpPoints(geom)).*
					from public.indec_e0211poligono where frac||radio = '".$fr."'  and mzatxt =  '".$mzaSig."'
					)
					 select
					 path[3]::integer as puntoid
					 from dumpmza
					 where st_astext(geom) = '".$wkt."'
					 limit 1
					";
					$puntosigid = $mbd->prepare($puntosigidsql);
					$puntosigid->execute();
					$puntosigid_res = $puntosigid->fetch(PDO::FETCH_ASSOC);
*/


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





                // Resultados por radio
                $respuestamza[$x] = [
                  'radio' => $fr,
                  'manzana_cant'=> count($arrayMZAOK),
                  'manzana_tipo'=> $mzaTipo,
                  'manzana_pos' => $x+1,
                  'manzana_act'=>$mzaAct,
                  'manzana_sig' => $mzaSig,
                  'entremanzanas_linea' => $fila['intersect_lineamza'],
                  'entremanzanas_punto_interseccion' => $wkt,
        				  'pgr_verticeid_old'=>$pgr_verticeid_old,
                  'pgr_verticeid' => $pgr_vertix_res['pgr_vertix_id']




                ];








    		}

  }
  elseif ( !empty($mzaSig   ) ) {





			foreach($mbd->query( "
		SELECT
		distinct

		st_astext(
      ST_LineMerge(
    		st_intersection(
    		(select distinct geom from public.indec_e0211poligono where frac||radio = '".$fr."'  and mzatxt =  '".$mzaAct."'  ),
    		(select distinct geom from public.indec_e0211poligono where frac||radio = '".$fr."'  and mzatxt =  '".$mzaSig."'  )
		    )
     )
		) intersect_lineamza,

		st_astext(
      st_geometryn(
      ST_CollectionHomogenize(
        st_intersection(
			(
			st_intersection(
			(select distinct geom from public.indec_e0211poligono where frac||radio = '".$fr."'  and mzatxt =  '".$mzaAct."'  ),
			(select distinct geom from public.indec_e0211poligono where frac||radio = '".$fr."'  and mzatxt =  '".$mzaSig."'  )
			)
			),
			(
			select ST_Boundary(st_union(geom)) from indec_e0211poligono where frac||radio = '".$fr."'
			)
		 )
   ),1)
		) intersect_lineabound

		FROM  public.indec_e0211poligono
		WHERE frac||radio = '".$fr."'
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


		  // Resultados por manzana
		  $respuestamza[$x] = [
		    'radio' => $fr,
        'manzana_cant'=> count($arrayMZAOK),
        'manzana_tipo'=> $mzaTipo,
        'manzana_pos' => $x+1,
		    'manzana_act'=>$mzaAct,
		    'manzana_sig' => $mzaSig,
		    'entremanzanas_linea' => $fila['intersect_lineamza'],
		    'entremanzanas_punto_interseccion' => $wkt,
			  'pgr_verticeid_old'=>$pgr_verticeid_old,
        'pgr_verticeid' => $pgr_vertix_res['pgr_vertix_id']

		  ];



		}





	} //mza 2

else {
  // Resultados de la ultima manzana (fin de la secuencia)
  // Esta manzana es el inicio del subconjunto de manzanas no boundaries en caso de contener las mismas.
  $respuestamza[$x] = [
    'radio' => $fr,
    'manzana_cant'=> count($arrayMZAOK),
    'manzana_tipo'=> $mzaTipo,
    'manzana_pos' => $x+1,
    'manzana_act'=>$mzaAct,
    'manzana_sig'=> null,
    'entremanzanas_linea' => null,
    'entremanzanas_punto_interseccion' => null,
    //repite el ultimo vertice porque es de 1 ruteo (round route desde hacia mismo vertice)
    'pgr_verticeid_old'=> $pgr_vertix_res['pgr_vertix_id'],
    'pgr_verticeid' => ''

  ];

}

} // mzasok



$mbd = null;

// datos del radio
$respuesta = [
'fraccionradio' => $fr,
'orden_mzas' =>$arrayMZAOK,
'orden_detalle ' =>$respuestamza
];

echo json_encode( $respuesta);

} catch (PDOException $e) {
    print "¡Error!: " . $e->getMessage() . "<br/>";
    die();
}

?>
