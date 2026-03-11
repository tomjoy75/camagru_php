# AI-Assisted Development Workflow

This document describes the standard workflow used to develop projects
with AI assistance (Cursor, ChatGPT, etc.).

Goals: - Maintain architectural clarity - Break the project into small
testable units - Guide the AI with structured context

------------------------------------------------------------------------

# 1. Project Context

Before starting implementation, gather the core context.

## Inputs

-   Project subject / specification
-   Constraints (language, framework, architecture)
-   Expected features

## Suggested folder structure

docs/ \
├── subject.txt \
├── WORKFLOW.md \
├── product_spec.md \
├── architecture.md \
├── feature_tree.md \
└── specs/

------------------------------------------------------------------------

# 2. Product Specification

Describe **what the product is supposed to do** at a high level.

### AI Prompt

```
Read the project subject @subject.txt.

Write a product specification including:

- project goal
- core features
- user flows
- constraints
```

## Output

docs/product_spec.md

## Example structure

# Product Specification

Goal \
... \
Describe the objective of the application.

Core Features 
- authentication 
- editor 
- gallery 
- comments 
- likes 
- notifications

Constraints 
- language 
- frameworks 
- security requirements

------------------------------------------------------------------------

# 3. Architecture Definition

Define **how the system will be built**.

(Adapt layers and components to your stack; the example below is a web/MVC case.)

### AI Prompt

```
Read docs/product_spec.md.

Propose a software architecture including:

- application layers
- controllers
- services
- repositories
- database structure
```

## Output

docs/architecture.md

## Example structure

# Architecture

Pattern\
MVC Monolith

Controllers 
- AuthController 
- EditorController 
- GalleryController

Services 
- AuthService 
- ImageService 
- NotificationService

Repositories 
- UserRepository 
- ImageRepository 
- CommentRepository

Database Tables 
- users 
- images 
- comments 
- likes

------------------------------------------------------------------------

# 4. Feature Tree

Break the product into **modules, features, and subfeatures**.

### AI Prompt

```
Read docs/product_spec.md and docs/architecture.md. If a codebase already exists, consider it.
Generate a hierarchical feature tree.

Structure it like:

Product
→ modules
→ features
→ subfeatures

Optional focus:

- authentication
- editor
- gallery
- comments
- likes
- notifications
```

## Output

docs/feature_tree.md

## Example

Camagru \
│ \
├── Authentication \
│ ├── register \
│ ├── login \
│ └── logout \
│ \
├── Editor \
│ ├── upload photo \
│ ├── select sticker \
│ ├── compose image \
│ └── save image \
│ ├── Gallery \
│ ├── view images \
│ ├── pagination \
│ └── delete image

------------------------------------------------------------------------

# 5. Feature Selection

Select the **next smallest logical unit** to implement.

### AI Prompt

```
Read docs/feature_tree.md and docs/architecture.md.

Identify the next smallest logical and testable unit to implement.

The unit must be:
- implementable in one development step
- testable independently
- not dependent on multiple unfinished features

Generate:
1. short feature specification
2. minimal implementation plan
3. controllers/services involved

Do not write code.
```

------------------------------------------------------------------------

# 6. Feature Specification

Create a spec file for the feature.

## File

docs/specs/`<feature_name>`.md

## Template

# Feature: `<name>`

Goal Describe the objective.

Behavior Describe expected behavior.

Constraints Technical or security constraints.

Success Criteria How we know the feature works.

------------------------------------------------------------------------

# 7. Implementation Plan

Break the feature into **small implementation steps** and append the plan to the spec file.

### AI Prompt

```
Read docs/specs/<feature_name>.md.

Break the feature into small implementation steps.

Rules:
- Each step should be small and independently testable.
- The plan should contain between 3 and 7 steps.
- Each step should require less than ~30 lines of code.
- Avoid explanations; only list steps.

Append the result in the same file (docs/specs/<feature_name>.md) under a new section titled "## Implementation Plan".
```

Example output format:

1 create route
2 validate input
3 call service
4 store data
5 return response

------------------------------------------------------------------------

NB. Before implementing a step, make sure you understand what it does.
If unclear, ask the AI to explain the step before coding.

### Example prompt (explain a step)

```
Explain step <n> of the implementation plan in simple terms without writing code.
```

------------------------------------------------------------------------

# 8. Implementation Loop

Workflow for each step:

Feature Spec/
↓/
Implementation Plan/
↓/
Understand Steps/
↓/
Generate Test Cases/
↓/
Create Issue + Branch
↓/
Implement steps (commit per step)/
↓/
Run tests/
↓/
Fix if needed/
↓/
Close issue & merge

Recommended git workflow:

git checkout -b feature/<feature_name>

Commit example:

feat(editor): add upload endpoint

------------------------------------------------------------------------

# 9. Test cases and Issue Creation

## Generate Tests

### AI Prompt

```
Read docs/specs/<feature_name>.md.

Generate tests before implementing features. Generate tests for this feature.

Include:
- success cases
- failure cases
- edge cases

Keep the output concise.
```

## Generate Github Issue

### AI Prompt

```
Read docs/specs/<feature_name>.md.

Generate a GitHub issue body for this feature.

Include:
- feature description
- implementation plan as checkboxes
- test cases as checkboxes
```

## Create branch

------------------------------------------------------------------------
# 10. Iteration Cycle

Repeat the cycle:

feature_tree\
↓\
select smallest unit\
↓\
feature spec\
↓\
implementation plan\
↓\
code\
↓\
tests

------------------------------------------------------------------------

# 11. Project Evolution

As the project grows:

-   update feature_tree.md
-   update architecture.md
-   add new specs in docs/specs/

These documents serve as **AI context sources**.

------------------------------------------------------------------------

# Core Principles

-   Smallest testable unit first
-   Controllers remain thin
-   Business logic belongs in services
-   Repositories handle persistence
-   Views render data only
-   Architecture documents guide AI decisions

Treat the implementation plan as the source of truth. If you need to change it, update the plan first (e.g. with AI), then implement. Do not redefine steps ad hoc during implementation.


------------------------------------------------------------------------

# Final workflow

Product Spec\
↓\
Architecture\
↓\
Feature Tree\
↓\
Select Feature\
↓\
Feature Spec\
↓\
Implementation Plan\
↓\
Understand Steps\
↓\
Generate Test Cases\
↓\
Create Issue + Branch\
↓\
Implement Steps (commit per step)\
↓\
Run Tests\
↓\
Fix\
↓\
Close Issue
