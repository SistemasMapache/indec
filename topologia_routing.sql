
-- testing script desde la perspectiva de topologia

-- public.indec_e0211linea : lineas de manzana

-- recomendamos homogenizar geometrias : ST_CollectionHomogenize
-- https://postgis.net/docs/ST_CollectionHomogenize.html
-- si la geometria es lineas y esta como multilinestring va a quedar como linestring.
-- ej   SELECT ST_AsText(ST_CollectionHomogenize('GEOMETRYCOLLECTION(POINT(0 0))'));
-- Res: POINT(0 0)


-- agregando columnas target y source a las lineas de indec:
ALTER TABLE public.indec_e0211linea ADD COLUMN target integer;
ALTER TABLE public.indec_e0211linea ADD COLUMN source integer;


--pgr_createTopology — Builds a network topology based on the geometry information.
--https://docs.pgrouting.org/2.2/en/src/topology/doc/pgr_createTopology.html
SELECT pgr_createTopology('indec_e0211linea', 0.0001, 'geom', 'id');

/*
NOTICE:  PROCESSING:
NOTICE:  pgr_createTopology('indec_e0211linea', 0.0001, 'geom', 'id', 'source', 'target', rows_where := 'true', clean := f)
NOTICE:  Performing checks, please wait .....
NOTICE:  Creating Topology, Please wait...
NOTICE:  1000 edges processed
NOTICE:  2000 edges processed
NOTICE:  -------------> TOPOLOGY CREATED FOR  2840 edges
NOTICE:  Rows with NULL geometry or NULL id: 0
NOTICE:  Vertices table for table public.indec_e0211linea is: public.indec_e0211linea_vertices_pgr
NOTICE:  ----------------------------------------------

Total query runtime: 5.2 secs
1 row retrieved.

*/

-- tener en cuenta:
--pgr_nodeNetwork - Crea los nodos de una tabla de bordes de la red.
--https://docs.pgrouting.org/2.0/es/src/common/doc/functions/node_network.html
--https://gis.stackexchange.com/questions/184332/how-does-pgr-createtopology-assign-source-and-target

-- CHECK TOPO
--pgr_analyzeGraph — Analyzes the network topology.
--https://docs.pgrouting.org/2.2/en/src/topology/doc/pgr_analyzeGraph.html#pgr-analyze-graph

SELECT pgr_analyzeGraph('indec_e0211linea',0.0001,'geom','id','source','target','true');

/*
NOTICE:  PROCESSING:
NOTICE:  pgr_analyzeGraph('indec_e0211linea',0.0001,'geom','id','source','target','true')
NOTICE:  Performing checks, please wait ...
NOTICE:  Analyzing for dead ends. Please wait...
NOTICE:  Analyzing for gaps. Please wait...
NOTICE:  Analyzing for isolated edges. Please wait...
NOTICE:  Analyzing for ring geometries. Please wait...
NOTICE:  Analyzing for intersections. Please wait...
NOTICE:              ANALYSIS RESULTS FOR SELECTED EDGES:
NOTICE:                    Isolated segments: 0
NOTICE:                            Dead ends: 2
NOTICE:  Potential gaps found near dead ends: 0
NOTICE:               Intersections detected: 0
NOTICE:                      Ring geometries: 0

Total query runtime: 335 msec
1 row retrieved.

*/
-- Dead ends: 2
-- OK

-- alter owner a tabla creada por pgrouting (de postgres al usuario de conexion del script, en este caso 'indec')
ALTER TABLE public.indec_e0211linea_vertices_pgr OWNER TO indec;

--check
select * from indec_e0211linea LIMIT 100;

-- check de radio:
select * from indec_e0211linea where ( mzai like '020770100108%' or mzad like '020770100108%' );


----
-- instalacion pgrouting 3.0.0 dev
-- https://docs.pgrouting.org/dev/en/pgRouting-installation.html

-- routing functions:
-- https://docs.pgrouting.org/dev/en/routingFunctions.html

-- Chinese Postman funcion pgrouting 3.0.0 dev
-- https://docs.pgrouting.org/dev/en/pgr_directedChPP.html
-- pgr_directedChPP — Calculates the shortest circuit path which contains every edge in a directed graph and starts and ends on the same vertex.

-- test chpp sobre radio 020770100108, solo respuesta chpp
with chpp as (
SELECT * FROM pgr_directedChPP(
    'SELECT id,
     source, target,
     st_length(geom4326::geography, true)/100000 as cost, st_length(geom4326::geography, true)/100000 as reverse_cost FROM public.indec_e0211linea where ( mzai like ''020770100108%'' or mzad like ''020770100108%'' )'
)
)
select * from chpp

-- test chpp sobre radio 020770100108, respuesta chpp y join a tabla linestrings y alturas.
 WITH chpp AS (
	SELECT
            pgr_directedchpp.seq,
            pgr_directedchpp.node,
            pgr_directedchpp.edge,
            pgr_directedchpp.cost,
            pgr_directedchpp.agg_cost
	FROM pgr_directedchpp(
		   'SELECT id, source, target, length as cost, length reverse_cost
		    FROM public.indec_e0211linea
		    WHERE (mzai like ''020770100107%'' or mzad like ''020770100107%'' )'::text
            )
            pgr_directedchpp(seq, node, edge, cost, agg_cost)
        )

 SELECT
    tipo as calletipo,
    nombre as callenombre,

    CASE
	    WHEN mzai like '020770100107%' then desdei
	    ELSE desded
    END as desde,

    CASE
	    WHEN mzai like '020770100107%' then hastai
	    ELSE hastad
    END as hasta,

    chpp.seq-1 as seq,
    chpp.node,
    chpp.edge,
    chpp.cost,
    chpp.agg_cost,
    indec_e0211linea.*
   FROM chpp
        JOIN public.indec_e0211linea ON indec_e0211linea.id = chpp.edge
   WHERE agg_cost > 0
   ORDER BY seq;




---ids boundary: identificar ids de radio que tocan el boundary
SELECT
id, source, target, length as cost, length reverse_cost
FROM indec_e0211linea
WHERE ( mzai like '020770100107%' or mzad like '020770100107%' )
and st_intersects
(geom,
    (
    SELECT st_boundary(ST_BuildArea(ST_Collect(geom)))
    FROM public.indec_e0211linea
    WHERE (mzai like '020770100107%' or mzad like '020770100107%' )
    )
) = 't'
and st_astext(ST_CollectionExtract(st_intersection(geom,
    (
    SELECT st_boundary(ST_BuildArea(ST_Collect(geom)))
    FROM public.indec_e0211linea
    WHERE (mzai like '020770100107%' or mzad like '020770100107%' )
    )
),1)) in ('POINT EMPTY','MULTIPOINT EMPTY')



-- ids sin boundary: identificar ids de radio que no tocan el boundary
SELECT
id, source, target, length as cost, length reverse_cost
FROM indec_e0211linea
where  (mzai like '020770100107%' or mzad like '020770100107%' ) and id not in
(
	SELECT
	id
	FROM public.indec_e0211linea
	WHERE ( mzai like '020770100107%' or mzad like '020770100107%' )
	and st_intersects
	(geom,
	    (
	    SELECT st_boundary(ST_BuildArea(ST_Collect(geom)))
	    FROM public.indec_e0211linea
	    WHERE (mzai like '020770100107%' or mzad like '020770100107%' )
	    )
	) = 't'
	and st_astext(ST_CollectionExtract(st_intersection(geom,
	    (
	    SELECT st_boundary(ST_BuildArea(ST_Collect(geom)))
	    FROM indec_e0211linea
	    WHERE (mzai like '020770100107%' or mzad like '020770100107%' )
	    )
	),1)) in ('POINT EMPTY','MULTIPOINT EMPTY')
)



-- chpp solo sobre ids que SI tocan el outer boundary del radio:

 WITH chpp AS (
	SELECT
            pgr_directedchpp.seq,
            pgr_directedchpp.node,
            pgr_directedchpp.edge,
            pgr_directedchpp.cost,
            pgr_directedchpp.agg_cost
	FROM pgr_directedchpp(
		'
			SELECT
			id, source, target, length as cost, length reverse_cost
			FROM indec_e0211linea
			WHERE ( mzai like ''020770100107%'' or mzad like ''020770100107%'' )
			and st_intersects
			(geom,
			    (
			    SELECT st_boundary(ST_BuildArea(ST_Collect(geom)))
			    FROM indec_e0211linea
			    WHERE (mzai like ''020770100107%'' or mzad like ''020770100107%'' )
			    )
			) = ''t''
			and st_astext(ST_CollectionExtract(st_intersection(geom,
			    (
			    SELECT st_boundary(ST_BuildArea(ST_Collect(geom)))
			    FROM indec_e0211linea
			    WHERE (mzai like ''020770100107%'' or mzad like ''020770100107%'' )
			    )
			),1)) in (''POINT EMPTY'',''MULTIPOINT EMPTY'')
		'::text
            )
            pgr_directedchpp(seq, node, edge, cost, agg_cost)
        )

 SELECT
    tipo as calletipo,
    nombre as callenombre,

    CASE
	    WHEN mzai like '020770100107%' then desdei
	    ELSE desded
    END as desde,

    CASE
	    WHEN mzai like '020770100107%' then hastai
	    ELSE hastad
    END as hasta,

    chpp.seq-1 as seq,
    chpp.node,
    chpp.edge,
    chpp.cost,
    chpp.agg_cost,
    indec_e0211linea.*
   FROM chpp
        JOIN indec_e0211linea ON indec_e0211linea.id = chpp.edge
   WHERE agg_cost > 0
   ORDER BY seq;




-- chpp solo sobre ids que NO tocan el outer boundary del radio:

 WITH chpp AS (
	SELECT
            pgr_directedchpp.seq,
            pgr_directedchpp.node,
            pgr_directedchpp.edge,
            pgr_directedchpp.cost,
            pgr_directedchpp.agg_cost
	FROM pgr_directedchpp(
		'
SELECT
id, source, target, length as cost, length reverse_cost
FROM indec_e0211linea
where  (mzai like ''020770100107%'' or mzad like ''020770100107%'' ) and id not in
(
	SELECT
	id
	FROM indec_e0211linea
	WHERE ( mzai like ''020770100107%'' or mzad like ''020770100107%'' )
	and st_intersects
	(geom,
	    (
	    SELECT st_boundary(ST_BuildArea(ST_Collect(geom)))
	    FROM public.indec_e0211linea
	    WHERE (mzai like ''020770100107%'' or mzad like ''020770100107%'' )
	    )
	) = ''t''
	and st_astext(ST_CollectionExtract(st_intersection(geom,
	    (
	    SELECT st_boundary(ST_BuildArea(ST_Collect(geom)))
	    FROM public.indec_e0211linea
	    WHERE (mzai like ''020770100107%'' or mzad like ''020770100107%'' )
	    )
	),1)) in (''POINT EMPTY'',''MULTIPOINT EMPTY'')
)

				'::text
            )
            pgr_directedchpp(seq, node, edge, cost, agg_cost)
        )

 SELECT
    tipo as calletipo,
    nombre as callenombre,

    CASE
	    WHEN mzai like '020770100107%' then desdei
	    ELSE desded
    END as desde,

    CASE
	    WHEN mzai like '020770100107%' then hastai
	    ELSE hastad
    END as hasta,

    chpp.seq-1 as seq,
    chpp.node,
    chpp.edge,
    chpp.cost,
    chpp.agg_cost,
    indec_e0211linea.*
   FROM chpp
        JOIN public.indec_e0211linea ON indec_e0211linea.id = chpp.edge
   WHERE agg_cost > 0
   ORDER BY seq;



-- todo:
-- mix:
-- camino largo y camino corto (longest path + shortest path)
-- outside boundary e inside lineas (ChPP)

-- diseño de recorrido con chpp
-- diseño de recorrido con caminoCorto + caminoLargo entre nodos de intersecciones de recorridos de manzanas.



--
-- id del vertice donde la geometria asociada al vertice es igual al punto de interseccion entre manzanas adyacentes:
-- este id sale del wktnode del script recuperaManzana01, esto es, la geometria del punto adyacente entre manzanas.
select id
    from
    public.indec_e0211linea_vertices_pgr
    where st_astext(the_geom) = 'POINT(5636669 6171998.5)' limit 1;





-- multiple path routing (de,hasta,cantidad de caminos)
-- para la primera manzana y la ultima manzana, este recorrido es 1 solo, y deberia iniciar y finalizar en el mismo id de vertice.
-- para las manzanas que no sean ni la primera ni la ultima, se generan 2 recorridos entre id vertice de adyacencia inicial y id vertice de adyacencia de manzana siguiente:

-- Returns the “K” shortest paths.
-- https://docs.pgrouting.org/dev/en/pgr_KSP.html
-- The K shortest path routing algorithm based on Yen’s algorithm. “K” is the number of shortest paths desired.

 SELECT * FROM pgr_ksp(
   'SELECT id,
    source, target,
    st_length(geomline::geography, true)/100000 as cost,
    st_length(geomline::geography, true)/100000 as reverse_cost
    FROM
    public.indec_e0211linea

    WHERE ( mzai like ''020770100108056'' or mzad like ''020770100108056'' )',
        68, -- id vertice inicial
        92, -- id vertice final
        2,  -- cantidad de recorridos
        true
    );



-- testing pgr_tsp


SELECT *
FROM pgr_tsp('
SELECT
	id::integer,
	st_x(the_geom) as x,
	st_y(the_geom) as y

FROM indec_e0211linea_vertices_pgr

WHERE id in (

	SELECT source as id FROM
	public.indec_e0211linea
	WHERE
	(
	mzai like ''%01080%'' or
	mzad like ''%01080%''
	)
	and ( mzai like ''%57'' or mzai like ''%56'' or mzai like ''%55'' or mzai like ''%58'' )

	union
	SELECT target as id FROM
	public.indec_e0211linea
	WHERE
	(
	mzai like ''%01080%'' or
	mzad like ''%01080%''
	)
	and ( mzai like ''%57'' or mzai like ''%56'' or mzai like ''%55'' or mzai like ''%58'' )


) ORDER BY id'

, 99);







SELECT
	id,
	st_x(the_geom) as x,
	st_y(the_geom) as y

FROM indec_e0211linea_vertices_pgr

WHERE id in (

	SELECT source as id FROM
	public.indec_e0211linea
	WHERE
	(
	mzai like '%01080%' or
	mzad like '%01080%'
	)
	and ( mzai like '%57' or mzai like '%56' or mzai like '%55' or mzai like '%58' )

	union
	SELECT target as id FROM
	public.indec_e0211linea
	WHERE
	(
	mzai like '%01080%' or
	mzad like '%01080%'
	)
	and ( mzai like '%57' or mzai like '%56' or mzai like '%55' or mzai like '%58' )


) ORDER BY id


-- https://docs.pgrouting.org/2.5/en/pgr_TSP.html
-- Returns a route that visits all the nodes exactly once.
-- pgr_TSP para vertice inicial y final mismo id.

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
      mzai like ''%01080%'' or
      mzad like ''%01080%''
      )
      AND
      (
      mzad like ''%58'' or
      mzai like ''%58''
      )
      ',
      ( SELECT array_agg(id) FROM indec_e0211linea_vertices_pgr ),
      directed := false
    )
    $$,
    start_id := 99,
    randomize := false
);


-- ejemplo de listado de viviendas segun segmento linea de manzana por recorrido

with callealt as  (
        SELECT DISTINCT
        58 as mzaid,
        g.id as lineaid,
        1 as path_id,
        1 as path_seq,
        g.tipo as tipocalle,
        g.nombre as nombrecalle,
        case
        when  g.mzad like '%58' then g.desded
        else g.desdei
        end desde,

        case
        when  g.mzad like '%58' then g.hastad
        else g.hastai
        end hasta,
	*
        FROM
        public.indec_e0211linea g


        join
        ( select * FROM indec_e0211linea_vertices_pgr where id in (99, 84) ) v
        on

        ( g.source = 99 and g.target = 84 ) or
        ( g.source = 84 and g.target = 99 )

)

select * from

indec_comuna11 viv

join callealt
on
viv.mza_comuna = 58 and
viv.cnombre = callealt.nombrecalle and
viv.hn between callealt.desde and callealt.hasta
order by cnombre asc,hn asc,hp desc, hd asc







---- listado de viviendas ordenadas


---- listado de viviendas ordenadas
with ruteo5 as (
with ruteo4 as (
with ruteo3 as (
with ruteo2 as (


    -- pgr_ksp
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
         mzai like ''%0108056'' or
         mzad like ''%0108056'' )',
         68,
         92,
         2,
         true
    ) where edge > 0



    )


  --ruteo2
  select
  56 as mzaid,
  linea.id as lineaid,
  linea.tipo,
  linea.nombre,
    case
          when linea.mzad like '%56' then linea.desded
          else linea.desdei
          end desde,

          case
          when linea.mzad like '%56' then linea.hastad
          else linea.hastai
          end hasta,

(
select hn from public.indec_geocoding_viviendas_indec geocode where ref_id = ruteo.edge
order by st_distance ( geocode.geom , ( select distinct the_geom from public.indec_e0211linea_vertices_pgr where id = 68 limit 1) ) limit 1
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
			'020110100108'
			)
		)
	,

	ST_GeometryType(
        			st_intersection(
		(
		select ST_CollectionHomogenize(geom) from public.indec_e0211linea l where l.id = edge
		),

		(
		select ST_CollectionHomogenize(ST_Boundary(ST_Union(geom))) FROM indec_e0211poligono
			where prov||depto||codloc||frac||radio in (
			'020110100108'
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

ROW_NUMBER () OVER (ORDER BY seq,
cnombre,
case when altura_orderby = 'HASTA' then geoc.hn end desc,
case when altura_orderby = 'DESDE' then geoc.hn end asc,
h4,hp ,hd) seqid_total,

ROW_NUMBER () OVER (PARTITION BY geoc.ref_id ORDER BY seq,
cnombre,
case when altura_orderby = 'HASTA' then geoc.hn end desc,
case when altura_orderby = 'DESDE' then geoc.hn end asc,
h4,hp ,hd) seqid_por_segmentolinea,


geoc.ref_id as geocref_id,
geoc.id as geocid,
geoc.hn as geochn,
geoc.cnombre as geoccnombre,
geoc.h4 as geoch4,
geoc.hp as geochp,
geoc.hd as geocdh,
geoc.geom as geocgeom,

ruteo3.*

from ruteo3
join public.indec_geocoding_viviendas_indec geoc on geoc.ref_id = ruteo3.edge
and MOD (desde::integer, 2) = MOD (geoc.hn::integer, 2)

order by
seq,
cnombre,
case when altura_orderby = 'HASTA' then geoc.hn end desc,
case when altura_orderby = 'DESDE' then geoc.hn end asc,
h4,hp ,hd
)

select

ntile((select count(distinct lineaid)::int from ruteo4)) over (order by seqid_total) cortecensista_por_ntile_cant_segmentos,
1 + ((seqid_total - 1) % 25) as cortecensista_cada20viviendas,
( select max(seqid_total) / count(distinct lineaid) from ruteo4 ) totviviendas_div_segmentos,

*
from ruteo4
order by seqid_total


)

select

(
select
count(cortecensista_por_ntile_cant_segmentos) as cant_cortecensista_por_ntile
from ruteo5 b where b.cortecensista_por_ntile_cant_segmentos = ruteo5.cortecensista_por_ntile_cant_segmentos
group by cortecensista_por_ntile_cant_segmentos
),
*

from ruteo5
order by seqid_total
