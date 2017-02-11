SELECT
	l.id,
	l.title,
	p.id AS id_first_item	
FROM
	labels AS l,
	relations_media_labels AS r,
	photos as p
WHERE
	l.id = r.id_labels
	AND
	r.id_media = p.id
	AND
	p.existing = 1
GROUP BY
	l.id
ORDER BY
	l.title
