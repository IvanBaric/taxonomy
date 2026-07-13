# Changelog

## [Unreleased]

### Changed
- Replaced package-local tenancy, UUID and slug infrastructure with Corexis traits and resolver contracts.
- Made taxonomy assignment actions operate on the same row instance they lock inside the transaction.
- Reduced package configuration to runtime options owned by taxonomy itself.

### Added
- `meta` JSON column on `taxonomy_items` for host-app specific attributes
- Automatic array casting for `meta` field
- Documentation and tests for meta usage
