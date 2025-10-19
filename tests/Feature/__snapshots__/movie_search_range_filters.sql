-- closed
select * from "movies" where "type" = 'movie' and exists (select 1 from json_each("genres") where "json_each"."value" is 'science fiction') and "year" between 1995 and 2005 and "runtime_min" between 90 and 140 and "imdb_rating" between 7.5 and 9.1
-- open-lower
select * from "movies" where "year" >= 2010 and "runtime_min" >= 100 and "imdb_rating" <= 8.4
-- open-upper
select * from "movies" where "year" <= 2012 and "runtime_min" <= 110 and "imdb_rating" >= 6.8
-- no-bounds
select * from "movies"
