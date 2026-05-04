# Contributing to Dixlase Blog

Thank you for your interest in contributing to **Dixlase Blog**. This plugin is part of the Dixlase Project, and all contributions are governed by the project-wide policies documented in the **Dixlase Core repository**.

The Japanese version of this guide is published as [CONTRIBUTING.ja.md](./CONTRIBUTING.ja.md).

---

## Project-wide policies (canonical)

The following documents in the Dixlase Core repository are authoritative and apply to all contributions, including contributions to this plugin:

- **[Contribution Guide](https://github.com/Dixlase/dixlase-core/blob/main/CONTRIBUTING.md)** — overall workflow, code style, testing, PR conventions
- **[Copyright Policy](https://github.com/Dixlase/dixlase-core/blob/main/COPYRIGHT-POLICY.md)** — high-level licensing stance
- **[Individual CLA](https://github.com/Dixlase/dixlase-core/blob/main/CLA-INDIVIDUAL.md)** — contributor license agreement for individuals
- **[Corporate CLA](https://github.com/Dixlase/dixlase-core/blob/main/CLA-CORPORATE.md)** — contributor license agreement for organizations

This plugin does **not** maintain its own copies of the CLA. The canonical CLA in the Core repository is the single source of truth. This avoids drift across plugin repositories.

## Why a CLA is required

**Dixlase Blog is dual-licensed** under GPL-3.0 + a commercial license offered by exc-D inc. Maintaining this dual-license model legally requires that exc-D be able to sublicense incoming contributions under both license tracks. The CLA grants exc-D the rights necessary to do so, while you retain ownership of your contributions.

In summary, by signing the CLA:

- You **retain ownership** of your contribution
- You **grant exc-D inc.** a perpetual, worldwide, irrevocable, sublicensable license sufficient to support the dual-license model
- You **agree not to assert moral rights** in a way that would prevent the exercise of that license
- You **confirm** you are authorized to grant the license (employer permission, original creation, third-party material disclosure)

## How to submit your CLA

While the Dixlase Project is in v0.1.x, CLA submission is handled by email:

1. Read the canonical [Individual CLA](https://github.com/Dixlase/dixlase-core/blob/main/CLA-INDIVIDUAL.md) (and [Corporate CLA](https://github.com/Dixlase/dixlase-core/blob/main/CLA-CORPORATE.md) if applicable) in full
2. Fill in the contributor information fields and sign at the bottom
3. Email the completed file to **office@exc-d.com** with the subject `CLA submission — <your name or organization>` and mention which plugin(s) you intend to contribute to

A single CLA covers contributions to the entire Dixlase Project — Core and all official plugins. You do not need to sign separate CLAs per repository.

In a future v0.1.x release, this manual workflow will be replaced by [CLA Assistant](https://cla-assistant.io/), which automates signature collection in the PR flow. When that happens, this section will be updated.

## Submitting a pull request

1. Fork this repository and create a feature branch
2. Make your changes following the conventions in the [Core Contribution Guide](https://github.com/Dixlase/dixlase-core/blob/main/CONTRIBUTING.md)
3. Add tests covering your changes
4. Ensure all tests pass and code is formatted (`vendor/bin/pint`)
5. Open a pull request against this plugin's `main` branch
6. Reference any related issue numbers
7. The maintainers will review and provide feedback

## Reporting issues

- **Bugs and feature requests for Dixlase Blog**: open an issue in this plugin's repository
- **Issues spanning multiple plugins or the Core**: open an issue in the [Dixlase Core repository](https://github.com/Dixlase/dixlase-core/issues)

## Code of Conduct

All contributions to Dixlase Blog and the wider Dixlase Project are subject to the [Dixlase Code of Conduct](https://github.com/Dixlase/dixlase-core/blob/main/CODE_OF_CONDUCT.md), if one is published in the Core repository.

---

**Contact:** office@exc-d.com
