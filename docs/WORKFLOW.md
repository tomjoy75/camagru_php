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
├── WORKFLOW.md \
├── product_spec.md \
├── architecture.md \
├── feature_tree.md \
└── specs/

------------------------------------------------------------------------

# 2. Product Specification

Describe **what the product is supposed to do** at a high level.

## Prompt

Read the project subject @subject.txt.

Write a product specification including: 
- project goal 
- core features 
- user flows 
- constraints

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

## Prompt

Read docs/product_spec.md.

Propose a software architecture including: 
- application layers 
- controllers 
- services 
- repositories 
- database structure

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

## Prompt

Based on the current codebase and the project subject @subject.txt,
generate a hierarchical feature tree.

Structure it like:

Product\
→ modules\
→ features\
→ subfeatures

Optional focus:

-   authentication
-   editor
-   gallery
-   comments
-   likes
-   notifications

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

## Prompt

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

Read docs/specs/<feature_name>.md
Break the feature into **small implementation steps**.

Rules:
- Each step should be **small and idependently testable**.
- the plan should contain between 3 and 7 steps
- avoid explanations, only list steps

Example:

1 create route\
2 validate input\
3 call service\
4 store data\
5 return response

Append the result in the same file (docs/specs/`<feature_name>`.md) under a new section titled:
 "## Implementation Plan"

------------------------------------------------------------------------

NB.
Before implementing a step, make sure you understand what it does.

If unclear, ask the AI to explain the step before coding.

Example prompt:

Explain step <n> of the implementation plan in simple terms
without writing code.

------------------------------------------------------------------------

# 8. Implementation Loop

Workflow for each step:

spec\
↓\
plan\
↓\
implement step\
↓\
test\
↓\
commit

Recommended git workflow:

git checkout -b feature/`<feature_name>`{=html}

Commit example:

feat(editor): add upload endpoint

------------------------------------------------------------------------

# 9. Testing

Read docs/specs/<feature_name>.md 
Generate tests before implementing features.

## Prompt

Generate tests for this feature.

Include: 
- success cases 
- failure cases 
- edge cases

Keep the output concise.

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
