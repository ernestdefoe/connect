import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import type Mithril from 'mithril';

const t = (key: string, params?: Record<string, unknown>) =>
  app.translator.trans(`ernestdefoe-connect.admin.${key}`, params);

interface Meta {
  events: { key: string; label: string }[];
  actions: { key: string; label: string; params: string[] }[];
  operators: string[];
  groups: { id: number; name: string }[];
  tags: { id: number; name: string }[];
}
interface Cond { field: string; op: string; value: string; }
interface Act { type: string; [p: string]: string; }
export interface RuleDraft {
  id?: number; name: string; event: string; enabled: boolean; match: string;
  conditions: Cond[]; actions: Act[];
}

/**
 * The trigger → conditions → actions builder for one rule. Deliberately plain:
 * dropdowns and rows, so a non-technical admin can assemble automations without
 * touching JSON.
 */
export default class RuleEditor extends Component<{ rule: RuleDraft; meta: Meta; onSave: (r: RuleDraft) => void; onCancel: () => void; saving?: boolean }> {
  private r!: RuleDraft;

  oninit(vnode: Mithril.Vnode<any, this>) {
    super.oninit(vnode);
    // work on a copy so Cancel discards cleanly
    const src = this.attrs.rule;
    this.r = JSON.parse(JSON.stringify({
      id: src.id, name: src.name || '', event: src.event || (this.attrs.meta.events[0]?.key ?? ''),
      enabled: src.enabled ?? true, match: src.match || 'all',
      conditions: src.conditions || [], actions: src.actions || [],
    }));
  }

  view() {
    const meta = this.attrs.meta;
    return (
      <div className="ConnectRule-editor">
        <div className="Form-group">
          <label>{t('rule_name')}</label>
          <input className="FormControl" value={this.r.name} placeholder={t('rule_name_placeholder') as string}
            oninput={(e: any) => { this.r.name = e.target.value; }} />
        </div>

        <div className="Form-group">
          <label>{t('rule_when')}</label>
          {Select.component({
            value: this.r.event,
            options: Object.fromEntries(meta.events.map((e) => [e.key, e.label])),
            onchange: (v: string) => { this.r.event = v; },
          })}
        </div>

        {/* Conditions */}
        <div className="Form-group">
          <label>{t('rule_conditions')}</label>
          <div className="ConnectRule-matchRow">
            {t('rule_match_pre')}
            {Select.component({ value: this.r.match, options: { all: t('rule_match_all'), any: t('rule_match_any') }, onchange: (v: string) => { this.r.match = v; } })}
            {t('rule_match_post')}
          </div>
          {this.r.conditions.map((c, i) => (
            <div className="ConnectRule-row">
              <input className="FormControl" placeholder={t('rule_field') as string} value={c.field}
                oninput={(e: any) => { c.field = e.target.value; }} />
              {Select.component({ value: c.op, options: Object.fromEntries(meta.operators.map((o) => [o, t('op_' + o)])), onchange: (v: string) => { c.op = v; } })}
              {['is_empty', 'is_not_empty'].includes(c.op) ? null : (
                <input className="FormControl" placeholder={t('rule_value') as string} value={c.value}
                  oninput={(e: any) => { c.value = e.target.value; }} />
              )}
              {Button.component({ className: 'Button Button--icon Button--flat', icon: 'fas fa-times', onclick: () => { this.r.conditions.splice(i, 1); } })}
            </div>
          ))}
          {Button.component({ className: 'Button Button--flat ConnectRule-add', icon: 'fas fa-plus', onclick: () => { this.r.conditions.push({ field: 'title', op: 'contains', value: '' }); } }, t('add_condition'))}
        </div>

        {/* Actions */}
        <div className="Form-group">
          <label>{t('rule_do')}</label>
          {this.r.actions.map((a, i) => (
            <div className="ConnectRule-row ConnectRule-actionRow">
              {Select.component({ value: a.type, options: Object.fromEntries(meta.actions.map((x) => [x.key, x.label])), onchange: (v: string) => { this.r.actions[i] = { type: v }; } })}
              {this.actionParams(a, meta)}
              {Button.component({ className: 'Button Button--icon Button--flat', icon: 'fas fa-times', onclick: () => { this.r.actions.splice(i, 1); } })}
            </div>
          ))}
          {Button.component({ className: 'Button Button--flat ConnectRule-add', icon: 'fas fa-plus', onclick: () => { this.r.actions.push({ type: meta.actions[0]?.key || 'reply' }); } }, t('add_action'))}
        </div>

        <div className="ConnectRule-actions">
          {Button.component({ className: 'Button Button--primary', loading: this.attrs.saving, onclick: () => this.attrs.onSave(this.r) }, t('save_rule'))}
          {Button.component({ className: 'Button Button--flat', onclick: () => this.attrs.onCancel() }, t('cancel'))}
        </div>
      </div>
    );
  }

  private actionParams(a: Act, meta: Meta): Mithril.Children {
    switch (a.type) {
      case 'reply':
        return <textarea className="FormControl" rows={2} placeholder={t('action_reply_content') as string}
          value={a.content || ''} oninput={(e: any) => { a.content = e.target.value; }} />;
      case 'add_tag':
      case 'remove_tag':
        return Select.component({ value: a.tagId || '', options: { '': '—', ...Object.fromEntries(meta.tags.map((tg) => [String(tg.id), tg.name])) }, onchange: (v: string) => { a.tagId = v; } });
      case 'add_to_group':
      case 'remove_from_group':
        return Select.component({ value: a.groupId || '', options: { '': '—', ...Object.fromEntries(meta.groups.map((g) => [String(g.id), g.name])) }, onchange: (v: string) => { a.groupId = v; } });
      case 'call_webhook':
        return <input className="FormControl" placeholder="https://…" value={a.url || ''} oninput={(e: any) => { a.url = e.target.value; }} />;
      default:
        return null;
    }
  }
}
