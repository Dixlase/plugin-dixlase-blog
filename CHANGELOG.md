# Changelog

All notable changes to the Dixlase Blog plugin are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this plugin follows Semantic Versioning.

## [Unreleased]

Work toward the first release, 0.1.0 (#13).

### Changed

- The built front-end assets under `resources/assets/` are no longer tracked in git, and
  their file names carry a content hash so browsers pick up a new build (#5). Run
  `npm run build` in a local checkout.
