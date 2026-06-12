# Changelog

## [2.5.0](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/compare/v2.4.0...v2.5.0) (2026-05-14)


### Features

* add EnsureTenantNotSuspended middleware + TenantStatusResolver contract ([facb705](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/facb705a8ca2008646faa1c67bac820f5832d208))
* add EnsureTenantNotSuspended middleware + TenantStatusResolver contract ([3089b17](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/3089b17f7a678ff0592c1d774f36a4269d4500ee))

## [2.4.0](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/compare/v2.3.0...v2.4.0) (2026-04-26)


### Features

* expose passwordSetToken on UserData ([11342a0](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/11342a0717eed7823442ae153225987bc116d8e9))
* expose passwordSetToken on UserData ([d009b4c](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/d009b4ce6b6b6b75b0702286882f17ff6bdfc078))

## [2.3.0](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/compare/v2.2.0...v2.3.0) (2026-04-20)


### Features

* add EnsurePermissionOrOwner middleware ([1695872](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/1695872bd372b21a038be3c7f247eb337fefa8f6))
* add EnsurePermissionOrOwner middleware ([1a8a934](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/1a8a934576aba0a4e11d593e80f710016fcba524))

## [2.2.0](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/compare/v2.1.0...v2.2.0) (2026-03-20)


### Features

* add jti claim, token blacklist, and encode metadata ([6f26a0a](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/6f26a0a20dd0d10720f40125cf705c80f0c364f8))
* add jti claim, token blacklist, and encode metadata ([3264d78](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/3264d78ce30585260c0368f5e28941e8e8b0672f))


### Bug Fixes

* resolve pint lint issues in middleware tests ([785a763](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/785a7633d3dda8dce519926e67cfc8499e5b5b38))


### Miscellaneous Chores

* add git-workflow skill with pre-commit quality checks ([4dc8d04](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/4dc8d04377843ae90a28d1fe15030c34ebaccc6b))

## [2.1.0](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/compare/v2.0.0...v2.1.0) (2026-03-05)


### Features

* add role filter support to UserClient::list() ([89b94e2](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/89b94e28c6694ed6b4c971e91c12915840a4f6e4))
* add role filter support to UserClient::list() ([08be22b](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/08be22b4f50465e614f48fa8b3f8efc5257cbfae))

## [2.0.0](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/compare/v1.3.0...v2.0.0) (2026-03-01)


### ⚠ BREAKING CHANGES

* change UserData and UserClient ID types from int to string

### Features

* change UserData and UserClient ID types from int to string ([66c68ad](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/66c68adaf5890180ef48909c99c614610b3d0509))

## [1.3.0](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/compare/v1.2.2...v1.3.0) (2026-03-01)


### Features

* change AuthContext ID types from int to string for UUID PKs ([d1bd309](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/d1bd309a097fbc556debac9d42de49b1e7fbdb0c))


### Miscellaneous Chores

* bump version to 2.0.0 for UUID PK migration ([176e1e9](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/176e1e940ab46c405fbc5bcf1698e5bb22016c39))

## [1.2.2](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/compare/v1.2.1...v1.2.2) (2026-02-24)


### Miscellaneous Chores

* fix format & stan ([c2da032](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/c2da03250f764c10d5122d98eaae5deea35639c8))
* rename veritypos-api references to veritypos-commerce-service ([7cf0d11](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/7cf0d1105d55a06db548a869601fc605ea08ec4a))
* rename veritypos-api to veritypos-commerce-service ([930efb1](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/930efb15cf7bef8bd0aba628b24524e495aa2fde))

## [1.2.1](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/compare/v1.2.0...v1.2.1) (2026-02-24)


### Bug Fixes

* remove /api and /auth prefix from client request URLs ([6fc6342](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/6fc63421400bb0063988403937ffb329699c50a6))


### Miscellaneous Chores

* **main:** release 1.2.0 ([159c9db](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/159c9db4f980c129f760deca706f80e2b51ce4a1))
* **main:** release 1.2.0 ([46ffe0a](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/46ffe0a6483a38d3eb4c975adb7809432fa899a9))

## [1.2.0](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/compare/v1.1.0...v1.2.0) (2026-02-24)


### Features

* add detect() method to UsernameType enum ([3056862](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/305686232dd60c41e08522764cc2b9fc02e17654))
* add RolePermissions static role-to-permissions map ([7a9309f](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/7a9309f021ea5879e8d0fabde80e983bcb0edac8))
* add shared auth enums (RolesEnum, PortalsEnum, UsernameType) ([a1ab3e1](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/a1ab3e1ad33278f9587baf3ccb0a03bb3aa32aaf))


### Bug Fixes

* **ci:** use pull_request_target and add pull-requests permission for PR lint ([e5acc6b](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/e5acc6bc602040abd778e85541bee6da0743db97))
* remove /api and /auth prefix from client request URLs ([6fc6342](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/6fc63421400bb0063988403937ffb329699c50a6))


### Miscellaneous Chores

* update default auth service port from 8003 to 8001 ([31df5ee](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/31df5ee2e34c28e6346e6e9c5a9720912e6bb93a))
* update default auth service port from 8003 to 8001 ([5e8fc6d](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/5e8fc6d09827cb6f013c13de14edfcc1e1759fd0))

## [1.1.0](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/compare/v1.0.0...v1.1.0) (2026-02-18)


### Features

* initial auth SDK — JWT AuthContext, middleware, HTTP clients ([a4f4571](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/a4f457117b1b29d401b68baedbdc89956d4e17f1))


### Miscellaneous Chores

* add AI tooling, CI workflows, and code quality config ([28efd9c](https://github.com/VerityCodeLabs-Group/veritypos-auth-sdk-php/commit/28efd9c4f6dbfa619e223a857d62b99a7cd20083))
