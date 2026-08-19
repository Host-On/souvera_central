# Changelog

## [0.40.60] — 2026-08

Repository moved to the Host-On organization. This release repoints the
self-update sources and neutralizes internal references for the open-source
publish.

## [0.40.59] — 2026-08

### Fixed

- Self-update swap uses copy+delete instead of rename() (NFS-safe, avoids
  open-file-handle errors).
