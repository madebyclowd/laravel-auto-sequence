---
bump: patch
type: Fixed
---

Documentation, the installer's printed setup steps, and the Boost
guideline/skill files referenced a `MadeByClowd\AutoSequence\Contracts\AutoSequence`
interface that doesn't exist — the actual contract is `Contracts\Sequenceable`.
All references now point to the correct interface.
