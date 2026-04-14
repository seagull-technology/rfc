# RFC Access Control Overview

## Purpose

This document explains, in simple terms, how we organized users, groups, roles, and permissions in the new RFC system.

The goal is to make sure:

- each person sees only what they should see
- each person can do only what they are allowed to do
- the system can support RFC staff, government authorities, companies, individual applicants, and platform admins in a clear way

## The Main Idea

We separated the access structure into 4 simple layers:

1. Group
2. Entity
3. User
4. Role and Permission

This makes the system easier to manage and safer in the long term.

## 1. Group

A group is the highest-level category of user or organization in the system.

The groups we created are:

- Authorities
- RFC
- Organizations
- Individuals
- Admins

Examples:

- Ministry of Interior belongs to `Authorities`
- Royal Film Commission belongs to `RFC`
- A production company belongs to `Organizations`
- A freelancer or individual producer belongs to `Individuals`
- Platform management team belongs to `Admins`

## 2. Entity

An entity is the real organization or real body inside a group.

This is very important because the system must know the exact party involved, not only the category.

Examples of entities:

- Royal Film Commission - Jordan
- Ministry of Interior
- Public Security Directorate
- Department of Antiquities
- A production company such as ABC Films
- An individual applicant such as John Doe

In simple words:

- Group = type
- Entity = actual party

## 3. User

A user is the actual person who logs into the system.

Examples:

- an RFC employee
- a reviewer from a ministry
- a company representative
- an individual applicant
- a platform admin

A user can be connected to an entity.

Example:

- Ahmad is a user
- Ahmad works for Ministry of Interior
- so Ahmad is linked to the entity `Ministry of Interior`

## 4. Role

A role describes the responsibility of the user inside the system.

Examples of roles:

- Super Admin
- Platform Admin
- RFC Admin
- RFC Intake Officer
- RFC Reviewer
- RFC Approver
- Authority Reviewer
- Authority Approver
- Applicant Owner
- Applicant Member

Important note:

A role is not the same as a group.

For example:

- `Authorities` is a group
- `Authority Reviewer` is a role

## 5. Permission

A permission is the exact action a role is allowed to do.

Examples:

- create application
- review application
- approve application
- reject application
- request clarification
- issue permit
- manage users
- export reports

In simple words:

- Group = where the user belongs
- Role = what responsibility they have
- Permission = what actions they can do

## How They Work Together

The structure works like this:

1. A user belongs to a real entity
2. The entity belongs to a group
3. The group has allowed roles
4. The user gets a role
5. The role gives permissions

Example 1:

- User: Sara
- Entity: Royal Film Commission - Jordan
- Group: RFC
- Role: RFC Reviewer
- Permissions: review applications, request clarifications, view assigned work

Example 2:

- User: Omar
- Entity: Ministry of Interior
- Group: Authorities
- Role: Authority Approver
- Permissions: review, approve, reject, and respond on authority-related requests

Example 3:

- User: Lina
- Entity: Future Films Company
- Group: Organizations
- Role: Applicant Owner
- Permissions: create and submit applications for her company

## Why This Structure Is Good

This structure is strong because:

- it is clear and easy to understand
- it supports many government entities
- it supports RFC internal teams
- it supports companies and individual applicants
- it allows future growth without redesigning everything
- it helps us control access safely and professionally

## What We Implemented in the First Step

In the current foundation, we already prepared:

- the 5 main groups
- the real entity layer
- user-to-entity membership
- the role structure
- the permission structure
- the connection between groups and allowed roles
- a starter list of important authorities and RFC as seed data

## Simple Summary

The system does not treat everyone as the same kind of user.

Instead, it asks 4 questions:

1. Which group does this party belong to?
2. What is the exact real entity?
3. Who is the logged-in person?
4. What role and permissions does that person have?

This gives us a professional base for the next stages:

- application submission
- RFC review
- authority approvals
- final permit issuance
- tracking, reports, and audit logs

