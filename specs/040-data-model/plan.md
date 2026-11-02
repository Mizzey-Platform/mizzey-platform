# Implementation Plan: the contracted data model

**Branch**: `040-data-model` | **Date**: 2026-11-02 | **Spec**: [spec.md](spec.md)

## Summary

A record (`data-model.md`) of where each ENT row lives, how it relates and how it behaves across the two languages, and a scenario (`t80-data-model`) that checks every storage the record names exists in the running store.

## Technical Context

| Item | Value |
|---|---|
| Code | None |
| Record | `specs/040-data-model/data-model.md` |
| Testing | `t80-data-model` on the local runtime and staging |

## Constitution Check

| Rule | How it is met |
|---|---|
| M-4 | Native structures named; the Mizzey ones point at their specs |
| M-7 | The record is checked by a scenario |
