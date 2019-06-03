
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
