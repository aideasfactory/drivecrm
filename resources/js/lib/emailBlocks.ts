/**
 * Turns an email body written with `{{#if name}}…{{else}}…{{/if}}` into a list
 * of paragraphs, each with a "shown to" rule, and back again.
 *
 * The body is still stored as text, so `EmailTemplateInterpolator` (PHP) keeps
 * sending emails exactly as before. A condition that wraps whole paragraphs
 * becomes that paragraph's rule; a condition inside a sentence (e.g.
 * "lesson/lessons") stays in the paragraph text as automatic wording.
 *
 * Whitespace matters: every line break in the body is a line in the sent
 * email. Each paragraph keeps the exact separator that came before it, and a
 * conditional paragraph carries its separator inside its condition so a
 * hidden paragraph leaves no blank line behind.
 */

export interface EmailBlockRule {
    key: string
    negate: boolean
}

export interface EmailBlock {
    id: string
    /** Whitespace before the paragraph ('' for the first, usually "\n\n"). */
    sep: string
    text: string
    /** Every rule must hold for the paragraph to show. */
    rules: EmailBlockRule[]
}

type TextNode = { type: 'text'; value: string }
type IfNode = {
    type: 'if'
    name: string
    rawOpen: string
    rawElse: string | null
    rawClose: string
    then: Node[]
    else: Node[] | null
}
type Node = TextNode | IfNode

const TAG = /(\{\{\{?\s*(?:#if\s+[a-z0-9_]+|else|\/if)\s*\}?\}\})/i
const PARAGRAPH_BREAK = /(\n[ \t]*\n(?:[ \t]*\n)*)/
const LEADING_LINE_BREAKS = /^(?:[ \t]*\n)+/
const INLINE_CONDITION = /\{\{\{?\s*#if\s+([a-z0-9_]+)\s*\}?\}\}/gi

export const PARAGRAPH_SEPARATOR = '\n\n'

let nextId = 0

export const newBlockId = (): string => `block-${Date.now()}-${nextId++}`

function tagType(tag: string): { type: 'if' | 'else' | 'end'; name: string } {
    const inner = tag.replace(/[{}\s]+/g, ' ').trim().toLowerCase()

    if (inner.startsWith('#if')) {
        return { type: 'if', name: inner.slice(3).trim() }
    }

    return { type: inner === 'else' ? 'else' : 'end', name: '' }
}

/**
 * Build the condition tree. Returns null for bodies the paragraph editor
 * can't represent faithfully (unbalanced tags or a second `{{else}}`); those
 * are edited as plain text.
 */
function tree(body: string): Node[] | null {
    const parts = body.split(TAG)
    const root: Node[] = []
    const stack: { node: IfNode; inElse: boolean }[] = []

    const target = (): Node[] => {
        const top = stack[stack.length - 1]

        if (!top) {
            return root
        }

        return top.inElse ? (top.node.else as Node[]) : top.node.then
    }

    for (let index = 0; index < parts.length; index++) {
        const part = parts[index]

        if (index % 2 === 0) {
            if (part !== '') {
                target().push({ type: 'text', value: part })
            }

            continue
        }

        const tag = tagType(part)

        if (tag.type === 'if') {
            const node: IfNode = {
                type: 'if',
                name: tag.name,
                rawOpen: part,
                rawElse: null,
                rawClose: '',
                then: [],
                else: null,
            }
            target().push(node)
            stack.push({ node, inElse: false })
            continue
        }

        const top = stack[stack.length - 1]

        if (!top) {
            return null
        }

        if (tag.type === 'else') {
            if (top.inElse) {
                return null
            }

            top.node.rawElse = part
            top.node.else = []
            top.inElse = true
            continue
        }

        top.node.rawClose = part
        stack.pop()
    }

    return stack.length === 0 ? root : null
}

function rawNodes(nodes: Node[]): string {
    return nodes
        .map((node) => {
            if (node.type === 'text') {
                return node.value
            }

            const elsePart =
                node.else !== null ? `${node.rawElse}${rawNodes(node.else)}` : ''

            return `${node.rawOpen}${rawNodes(node.then)}${elsePart}${node.rawClose}`
        })
        .join('')
}

/**
 * A condition stands on its own (and becomes a paragraph rule) when what
 * follows starts on a new line, and either nothing but whitespace precedes it
 * in the current paragraph or it carries its own line break.
 */
function startsOnNewLine(next: Node | undefined): boolean {
    if (!next || next.type === 'if') {
        return true
    }

    return /^[ \t]*\n/.test(next.value)
}

/**
 * Whether every branch starts on a new line, i.e. the condition carries its
 * own line break (how a conditional paragraph after other text is written).
 */
function leadsWithLineBreak(nodes: Node[]): boolean {
    const first = nodes[0]

    if (!first) {
        return true
    }

    if (first.type === 'text') {
        return /^[ \t]*\n/.test(first.value)
    }

    return leadsWithLineBreak(first.then) && leadsWithLineBreak(first.else ?? [])
}

function blockify(
    nodes: Node[],
    rules: EmailBlockRule[],
    out: EmailBlock[],
    leadingSep: string,
): void {
    let buffer = ''
    let pendingSep = leadingSep
    const firstIndex = out.length

    const flush = () => {
        if (buffer.trim() === '') {
            pendingSep += buffer
            buffer = ''

            return
        }

        const leading = buffer.match(LEADING_LINE_BREAKS)?.[0] ?? ''

        out.push({
            id: newBlockId(),
            sep: pendingSep + leading,
            text: buffer.slice(leading.length),
            rules: [...rules],
        })
        buffer = ''
        pendingSep = ''
    }

    nodes.forEach((node, index) => {
        if (node.type === 'text') {
            node.value.split(PARAGRAPH_BREAK).forEach((piece, pieceIndex) => {
                if (pieceIndex % 2 === 1) {
                    flush()
                    pendingSep += piece
                } else {
                    buffer += piece
                }
            })

            return
        }

        const standsAlone =
            buffer.trim() === '' ||
            (leadsWithLineBreak(node.then) && leadsWithLineBreak(node.else ?? []))

        if (standsAlone && startsOnNewLine(nodes[index + 1])) {
            flush()

            const sep = pendingSep + buffer
            buffer = ''
            pendingSep = ''

            blockify(node.then, [...rules, { key: node.name, negate: false }], out, sep)

            if (node.else !== null) {
                blockify(node.else, [...rules, { key: node.name, negate: true }], out, sep)
            }

            return
        }

        buffer += rawNodes([node])
    })

    flush()

    if (pendingSep !== '' && out.length > firstIndex) {
        out[out.length - 1].text += pendingSep
    }
}

/**
 * Split a body into paragraphs. Returns null when it can only be edited as
 * text.
 */
export function parseEmailBlocks(body: string): EmailBlock[] | null {
    const nodes = tree(body.replace(/\r\n?/g, '\n'))

    if (nodes === null) {
        return null
    }

    const blocks: EmailBlock[] = []
    blockify(nodes, [], blocks, '')

    return blocks
}

const openTags = (rules: EmailBlockRule[]): string =>
    rules
        .map((rule) => (rule.negate ? `{{#if ${rule.key}}}{{else}}` : `{{#if ${rule.key}}}`))
        .join('')

const closeTags = (rules: EmailBlockRule[]): string => '{{/if}}'.repeat(rules.length)

const sameRule = (a: EmailBlockRule, b: EmailBlockRule): boolean =>
    a.key === b.key && a.negate === b.negate

/**
 * Two neighbours that differ only in their last rule (shown when X / shown
 * when not X) are written as one `{{#if X}}…{{else}}…{{/if}}`.
 */
function isPair(first: EmailBlock, second: EmailBlock | undefined): boolean {
    if (!second || first.rules.length === 0 || first.rules.length !== second.rules.length) {
        return false
    }

    const last = first.rules.length - 1

    return (
        first.rules.slice(0, last).every((rule, index) => sameRule(rule, second.rules[index])) &&
        first.rules[last].key === second.rules[last].key &&
        !first.rules[last].negate &&
        second.rules[last].negate
    )
}

export function serializeEmailBlocks(blocks: EmailBlock[]): string {
    let body = ''

    for (let index = 0; index < blocks.length; index++) {
        const block = blocks[index]

        if (block.rules.length === 0) {
            body += block.sep + block.text
            continue
        }

        const next = blocks[index + 1]

        if (isPair(block, next)) {
            const prefix = block.rules.slice(0, -1)
            const key = block.rules[block.rules.length - 1].key

            body +=
                openTags(prefix) +
                `{{#if ${key}}}${block.sep}${block.text}{{else}}${next.sep}${next.text}{{/if}}` +
                closeTags(prefix)
            index++
            continue
        }

        body += openTags(block.rules) + block.sep + block.text + closeTags(block.rules)
    }

    return body
}

/** Condition names used inside a paragraph's own text. */
export function inlineConditionKeys(text: string): string[] {
    return [...new Set([...text.matchAll(INLINE_CONDITION)].map((match) => match[1].toLowerCase()))]
}

export function isActionButtonBlock(block: EmailBlock): boolean {
    return /^\s*\{\{\s*action_button\s*\}\}\s*$/i.test(block.text)
}

/**
 * Whether the paragraph shows for a preview where `enabled` conditions are
 * ticked. Keys that aren't tickable conditions (placeholders used as
 * conditions, e.g. a payment deadline) count as having a value.
 */
export function isBlockVisible(
    block: EmailBlock,
    enabled: string[],
    tickable: string[],
): boolean {
    return block.rules.every((rule) => {
        const truthy = tickable.includes(rule.key) ? enabled.includes(rule.key) : true

        return truthy !== rule.negate
    })
}
