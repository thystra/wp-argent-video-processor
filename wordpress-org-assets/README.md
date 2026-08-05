# WordPress.org assets

These files are source-controlled with the Git project but are not part of the
installable plugin ZIP.

After WordPress.org approves the plugin, copy only these files into the
top-level `assets/` directory of the assigned SVN repository:

- `icon-128x128.png`
- `icon-256x256.png`

Keep `source/argentwolf-video-processor-icon-1024.png` in Git as the editable
high-resolution master. Do not copy the source master or this README into SVN.

Set the SVN MIME property on both published PNG files:

```bash
svn propset svn:mime-type image/png assets/icon-128x128.png
svn propset svn:mime-type image/png assets/icon-256x256.png
```
