<?php
//header('Content-Type: application/json');

error_reporting(E_ALL); ini_set('display_errors', 1);
//error_reporting(E_ERROR | E_PARSE);

$respuesta             = [];




/*

objetivo del script: intentar clasificar atributos entre adyacentes a una manzana actual y las candidatas siguientes

Dado un conjunto de prov||depto||codloc||frac||radio , el script devuelve:

- id prov||depto||codloc||frac||radio del radio
- linestring boundary exterior del radio
- cantidad de manzanas que tocan boundary
- cantidad de manzanas que no tocan boundary
- bool si es un radio cuyo total de manzanas tocan boundary del radio
- para los radios con bool true (solo radios cuyo total de manzanas tocan boundary del radio):
    - mzaid (prov||depto||codloc||frac||radio|mza)
    - tipo de intersección entre manzana y boundary
    - por manzana adyacente a manzana actual
        - id de manzanas adyacentes a manzana actual
        - distancia de manzanas adyacentes y manzana actual




ej input having in :
'020110100210',
'020110100407',
'020110100106',
'020110100101',
'020110100705',
'020110100706'
*/

/*
having prov||depto||codloc||frac||radio in
(
'020110100108'
)
*/


// geometria de boundary por Radio: prov||depto||codloc||frac||radio
$bordes_fracciones_sql = "
WITH fracs001 AS (
  SELECT
  prov||depto||codloc||frac||radio as boundary_fracradio,
  ST_AsText(ST_Boundary(ST_Union(geom))) boundary_geom_astext,
  prov||depto||codloc as pdcl,
  frac||radio as fr
  FROM indec_e0211poligono
  GROUP BY prov||depto||codloc||frac||radio,prov||depto||codloc, frac||radio

)
  SELECT distinct
    ROW_NUMBER () OVER (ORDER BY boundary_fracradio) as id,
    boundary_fracradio,
    boundary_geom_astext,
    pdcl,
    fr
  FROM fracs001
  where
    pdcl = '02011010' and
    fr in ('0108','0107')

  ORDER BY id

    ";

try {


$mbd = new PDO('pgsql:host=localhost;dbname=gisdata', 'indec', 'indec');

//foreach radio
foreach($mbd->query( $bordes_fracciones_sql ) as $fila) {

  $res_manzanas_true     = [];
  $res_manzanas_false    = [];
  $res_manzanas_linderas_true = [];
  $res_manzanas_linderas_false = [];



    // manzanas true adyacencia boundary radio
    $manzanasBorderTrue_sql = "select
      distinct
      prov||depto||codloc||frac||radio||mza mza,
      st_union(geom) geom
      from indec_e0211poligono
      where
      prov||depto||codloc||frac||radio = '".$fila['boundary_fracradio']."'
      and st_touches(geom, ( select ST_GeomFromText('".$fila['boundary_geom_astext']."',4326) )) = 't'
      group by prov||depto||codloc||frac||radio||mza
      order by prov||depto||codloc||frac||radio||mza";
      $result_true = $mbd->prepare($manzanasBorderTrue_sql);
      $result_true->execute();
      $manzanasBorderTrue = $result_true->rowCount();


    // manzanas false adyacencia boundary radio
    $manzanasBorderFalse_sql = "select
      distinct
      prov||depto||codloc||frac||radio||mza mza,
      st_union(geom) geom
      from indec_e0211poligono
      where
      prov||depto||codloc||frac||radio = '".$fila['boundary_fracradio']."'
      and st_touches(geom, ( select ST_GeomFromText('".$fila['boundary_geom_astext']."',4326) )) = 'f'
      group by prov||depto||codloc||frac||radio||mza
      order by prov||depto||codloc||frac||radio||mza";
      $result_false = $mbd->prepare($manzanasBorderFalse_sql);
      $result_false->execute();
      $manzanasBorderFalse = $result_false->rowCount();


      if ( $manzanasBorderFalse == 0 ) {
        $manzanasNoBoundary = true;
        // el radio posee un unico boundary el cual intersecta con el total de manzanas.
      } else {
        $manzanasNoBoundary = false;
        // el radio posee mas manzanas no intersectadas por el boundary exterior.
      }






    // solo si son manzanas boundary
    // para manzanas true
      foreach($result_true as $rowmt) {
        array_push($res_manzanas_true, $rowmt['mza']);

        // datos de manzanas por radio
        $sql_manzanas_data = "
        with mzas_data as
        (

        	SELECT

        		ST_GeometryType(
          		st_intersection(
            		pol.geom,
            	  ST_GeomFromText('".$fila['boundary_geom_astext']."',4326)
          		)
        		) in ('ST_LineString', 'ST_MultiLineString', 'ST_GeometryCollection')
        		as tipo,

        		(
        		select
        		array_to_json(array_agg(polNext.prov||polNext.depto||polNext.codloc||polNext.frac||polNext.radio||polNext.mza))
        		from public.indec_e0211poligono as polNext
        			where
        			polNext.prov||polNext.depto||polNext.codloc||polNext.frac||polNext.radio = '".$fila['boundary_fracradio']."' and
        			st_intersects(pol.geom,polNext.geom)
        			and ST_GeometryType(st_intersection(pol.geom,polNext.geom)) in ('ST_LineString', 'ST_MultiLineString')
        		) as mza_touches,

        		(
        		select
        		array_to_json(array_agg(ST_Distance(st_centroid(pol.geom),ST_Centroid(polNext.geom))))
        		from public.indec_e0211poligono as polNext
        			where
        			polNext.prov||polNext.depto||polNext.codloc||polNext.frac||polNext.radio = '".$fila['boundary_fracradio']."' and
        			st_touches(pol.geom,polNext.geom)
        			and ST_GeometryType(st_intersection(pol.geom,polNext.geom)) in ('ST_LineString', 'ST_MultiLineString')
        		) as mza_touchescentdist,

        		ST_GeometryType(
        			st_intersection(
        			pol.geom,
        			ST_GeomFromText('".$fila['boundary_geom_astext']."',4326)
        			)
        		) geotype,


        		st_astext(
              ST_CollectionExtract(
            		st_intersection(
              		pol.geom,
              		ST_GeomFromText('".$fila['boundary_geom_astext']."',4326)
          		  ),
              1)
            ) colex



        	FROM
        		public.indec_e0211poligono as pol
        	WHERE

            prov||depto||codloc||frac||radio||mza = '".$rowmt['mza']."' and

        		ST_GeometryType(
        			st_intersection(
        			pol.geom,
        		  ST_GeomFromText('".$fila['boundary_geom_astext']."',4326)
        			)
        		) in ('ST_LineString', 'ST_MultiLineString', 'ST_GeometryCollection')

        )

        select tipo,  mza_touches ,mza_touchescentdist , geotype
        from mzas_data
        where colex in ('POINT EMPTY','MULTIPOINT EMPTY')
        ORDER BY length(mza_touches::text)";

        // Sumar valor (mts de adyacencia) lineal sobre boundary

        $result_manzanas_data = $mbd->prepare($sql_manzanas_data);
        $result_manzanas_data->execute();
        $result_manzanas_data_res = $result_manzanas_data->fetch(PDO::FETCH_ASSOC);



        if ($result_manzanas_data->rowCount() > 0 ) {

            $res_manzanas_linderas_true[ $rowmt['mza'] ] =
              array (
               'mza_id' => $rowmt['mza'],
               'mza_intersect_radio_tipo' => $result_manzanas_data_res['geotype'],
               'mza_touches' => json_decode($result_manzanas_data_res['mza_touches']),
               'mza_touchescentdist' => json_decode($result_manzanas_data_res['mza_touchescentdist'])
             );

        }







      } // foreach($result_true as $rowmt)











    // para manzanas false, es decir radios que contienen manzanas que no tocan boundary, se puede iterar las mismas creando un subradio y anexar las adyacentes al boundary del radio contra las adyacentes al boundary del subradio
    //se debe volver a iterar creando un nuevo boundary de manzanas boundary.
/*
      foreach($result_false as $rowmf) {
        array_push($res_manzanas_false, $rowmf['mza']);
      }
*/



























    // Resultados por radio
    $respuesta[$fila['id']-1] = [


      'radio' => $fila['boundary_fracradio'],
      'radio_pdcl' => $fila['pdcl'],
      'radio_fr' => $fila['fr'],
      'radio_manzanas_tocan_borde_true_cant' => $manzanasBorderTrue,
      'radio_manzanas_tocan_borde_false_cant' => $manzanasBorderFalse,
      'radio_manzanas_todas_borde_bool' => $manzanasNoBoundary


      /*
      'radio_geom' => $fila['boundary_geom_astext']
      ,
      'radio_manzanas_tocan_borde_true_cant' => $manzanasBorderTrue,
      'radio_manzanas_tocan_borde_false_cant' => $manzanasBorderFalse,
      'radio_manzanas_todas_borde_bool' => $manzanasNoBoundary,
      'radio_manzanas_tocan_borde_true_lista' => $res_manzanas_true,
      'radio_manzanas_tocan_borde_false_lista' => $res_manzanas_false,
      'res_manzanas_linderas_true' =>$res_manzanas_linderas_true,
      'res_manzanas_linderas_false' =>$res_manzanas_linderas_false
      */


    ];



}



foreach ($respuesta as $valrm ) {


  if ( $valrm['radio_manzanas_todas_borde_bool'] == true ) {

    print_r($valrm);

    $pdcl = $valrm['radio_pdcl'];
    $fr = $valrm['radio_fr'];

    echo "procesa ".$pdcl.$fr;
    $output = shell_exec("php /var/www/html/indec/01git/indec/routingeotopo.php --fr=".$fr." --pdcl=".$pdcl);

  } else {
  }

}

//echo json_encode( $respuesta, JSON_PRETTY_PRINT);




} catch (\PDOException $e) {
    echo $e->getMessage();
}
?>
