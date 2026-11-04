# Implementation Plan: the WhatsApp click-to-chat link

**Branch**: `059-whatsapp-contact` | **Date**: 2026-11-04 | **Spec**: [spec.md](spec.md)

## Summary

`MizzeySite\Storefront\ContactLinks`: three settings, the `wa.me` address, a REST route and a shortcode.

## Constitution Check

| Rule | How it is met |
|---|---|
| M-1 | US-01-07's WhatsApp criterion, and CMS-09's editing |
| M-4 | Pages and CoreX Forms are used as they are; only the link is ours |
| M-7 | Every criterion has a t99 check; deliberate defects are caught (`evidence/mutations.txt`) |

## Criterion to test map

| AC | `t99-whatsapp-contact` asserts |
|---|---|
| AC-1 | The English and Arabic links; a 00 number; the Arabic falling back to English |
| AC-2 | No link with no number or a short one |
| AC-3 | The REST route in both languages; a page with the shortcode |
| AC-4 | The three settings in the General settings |
