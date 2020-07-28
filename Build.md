Build:

`yarn run build`

Dev:
`yarn run dev`

Rsync:
`rsync -c --delete -rzpp --exclude '.git' --filter=':- .gitignore' <src> <mountedOnBga>`
