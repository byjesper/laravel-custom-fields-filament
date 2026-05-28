# Contributing

This package is private while it is being extracted and hardened, but it is
intended to become open source later. The workflow is intentionally lightweight
for solo development now and strict enough to support external contributions
later.

## Git Strategy

- `main` must always be releasable.
- Use branches for feature work, behavior changes, public UI/API changes,
  Filament resource behavior, or anything StaffSpring depends on before release.
- Tiny docs or metadata fixes may be committed directly to `main` while the
  package is private and solo-maintained.
- Once the package is public, protect `main` and require pull requests for all
  external contributions.
- Prefer issue-backed work. The issue does not need ceremony, but it should
  capture intent before larger changes begin.

## Branch Names

Use short, descriptive prefixes:

- `feat/...` for new capabilities
- `fix/...` for bugs
- `docs/...` for documentation-only changes
- `ci/...` for tooling and workflow changes

## Releases

Releases are tags from `main` using SemVer:

- Patch: bug fixes, docs, and internal cleanup.
- Minor: new UI behavior, supported field types, config options, or
  non-breaking integration changes.
- Major: removed public APIs, changed component contracts, or changes requiring
  a new incompatible core package version.

This package should only tag a release that depends on a core package version
that already exists. Consumer applications should normally depend on tagged
releases. Temporary branch constraints are acceptable during active StaffSpring
adoption, but they should be replaced with a tag quickly.

## Public Contribution Expectations

Before the repository is public, add the remaining community files:

- Pull request template
- Issue templates
- Code of conduct
- Security policy

External pull requests should pass CI before review and should include tests
for behavior changes.
