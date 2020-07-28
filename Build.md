Build:

`yarn run build`

Dev:
`yarn run dev`

Rsync:
`rsync -c --delete -rzpP --exclude '.git' --filter=':- .gitignore' <src> <mountedOnBga>`
