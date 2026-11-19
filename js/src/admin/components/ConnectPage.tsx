import app from 'flarum/admin/app';
import extractText from 'flarum/common/utils/extractText';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type { ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Switch from 'flarum/common/components/Switch';
import type Mithril from 'mithril';
import RuleEditor from './RuleEditor';
import type { RuleDraft } from './RuleEditor';
import ZapierPanel from './ZapierPanel';

const t = (key: string, params: Record<string, unknown> = {}) => app.translator.trans(`ernestdefoe-connect.admin.${key}`, params);

/** A JSON:API resource object flattened to {id, ...attributes}. */
const row = (resource: any) => ({ id: Number(resource.id), ...resource.attributes });
const rows = (document: any) => (document.data || []).map(row);

interface Key {
  id: number;
  label: string;
  /** The raw key, present only in the response that created it. */
  token: string | null;
  tokenPrefix: string | null;
  secret: string;
  scopes: string[];
  user: string | null;
  userId: number;
  hooks: number;
  lastUsedAt: string | null;
}
interface Sub {
  id: number;
  event: string;
  targetUrl: string;
  keyId: number;
  keyLabel: string | null;
}
interface Evt {
  key: string;
  label: string;
}
interface Rule {
  id: number;
  name: string;
  event: string;
  enabled: boolean;
  match: string;
  conditions: any[];
  actions: any[];
  runs: number;
}

/**
 * Connect admin page: create/revoke API keys, see which triggers exist, and
 * watch live subscriptions. The keys carry the secrets an admin pastes into
 * Zapier/Make: a new key is shown in full once, then by its prefix only.
 */
export default class ConnectPage extends ExtensionPage<ExtensionPageAttrs> {
  loading = true;
  private keys: Key[] = [];
  private subs: Sub[] = [];
  private events: Evt[] = [];
  private rules: Rule[] = [];
  private meta: any = null;
  private editing: RuleDraft | null = null;
  private savingRule = false;
  private zapierClientId = '';
  private zapierAppSlug = '';
  private savingZapier = false;
  private newLabel = '';
  private newScopes: Record<string, boolean> = { read: true, write: true };
  private creating = false;
  private revealed: Record<number, boolean> = {};

  oninit(vnode: Mithril.Vnode<ExtensionPageAttrs, this>) {
    super.oninit(vnode);
    this.zapierClientId = (app.data.settings['ernestdefoe-connect.zapier_client_id'] as string) || '';
    this.zapierAppSlug = (app.data.settings['ernestdefoe-connect.zapier_app_slug'] as string) || '';
    this.load();
  }

  private saveZapier(clientId: string, appSlug: string) {
    this.savingZapier = true;
    m.redraw();
    app
      .request({
        method: 'POST',
        url: `${this.api()}/settings`,
        body: {
          'ernestdefoe-connect.zapier_client_id': clientId,
          'ernestdefoe-connect.zapier_app_slug': appSlug,
        },
      })
      .then(() => {
        this.zapierClientId = clientId;
        this.zapierAppSlug = appSlug;
        app.data.settings['ernestdefoe-connect.zapier_client_id'] = clientId;
        app.data.settings['ernestdefoe-connect.zapier_app_slug'] = appSlug;
        this.savingZapier = false;
        app.alerts.show({ type: 'success' }, t('zapier_saved'));
        m.redraw();
      })
      .catch(() => {
        this.savingZapier = false;
        app.alerts.show({ type: 'error' }, t('rule_error'));
        m.redraw();
      });
  }

  private api() {
    return app.forum.attribute('apiUrl');
  }

  private load() {
    this.loading = true;
    Promise.all([
      app.request<any>({ method: 'GET', url: `${this.api()}/connect-keys` }),
      app.request<any>({ method: 'GET', url: `${this.api()}/connect-subscriptions` }),
      app.request<any>({ method: 'GET', url: `${this.api()}/connect-rules` }),
      app.request<any>({ method: 'GET', url: `${this.api()}/connect/meta` }),
    ])
      .then(([k, s, r, meta]) => {
        this.keys = rows(k);
        this.subs = rows(s);
        this.rules = rows(r);
        this.meta = meta.data || null;
        this.events = this.meta?.events || [];
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  private newRule() {
    this.editing = { name: '', event: this.events[0]?.key || '', enabled: true, match: 'all', conditions: [], actions: [] };
    m.redraw();
  }

  private saveRule(draft: RuleDraft) {
    this.savingRule = true;
    m.redraw();
    const { id, ...attributes } = draft;
    app
      .request<any>({
        method: id ? 'PATCH' : 'POST',
        url: `${this.api()}/connect-rules${id ? '/' + id : ''}`,
        body: { data: { type: 'connect-rules', ...(id ? { id: String(id) } : {}), attributes } },
      })
      .then((res) => {
        const saved = row(res.data);
        if (!id) this.rules.push(saved);
        else this.rules = this.rules.map((x) => (x.id === saved.id ? saved : x));
        this.editing = null;
        this.savingRule = false;
        app.alerts.show({ type: 'success' }, t('rule_saved'));
        m.redraw();
      })
      .catch(() => {
        this.savingRule = false;
        app.alerts.show({ type: 'error' }, t('rule_error'));
        m.redraw();
      });
  }

  private toggleRule(rule: Rule) {
    rule.enabled = !rule.enabled;
    app.request({
      method: 'PATCH',
      url: `${this.api()}/connect-rules/${rule.id}`,
      body: { data: { type: 'connect-rules', id: String(rule.id), attributes: { enabled: rule.enabled } } },
    });
  }

  private deleteRule(rule: Rule) {
    if (!confirm(t('confirm_delete_rule', { name: rule.name }) as unknown as string)) return;
    app.request({ method: 'DELETE', url: `${this.api()}/connect-rules/${rule.id}` }).then(() => {
      this.rules = this.rules.filter((r) => r.id !== rule.id);
      m.redraw();
    });
  }

  private create() {
    const label = this.newLabel.trim();
    if (!label || this.creating) return;
    const scopes = Object.keys(this.newScopes).filter((s) => this.newScopes[s]);
    this.creating = true;
    m.redraw();
    app
      .request<any>({
        method: 'POST',
        url: `${this.api()}/connect-keys`,
        body: { data: { type: 'connect-keys', attributes: { label, scopes } } },
      })
      .then((res) => {
        const key = row(res.data);
        this.keys.unshift(key);
        this.revealed[key.id] = true; // show the fresh secret immediately
        this.newLabel = '';
        this.creating = false;
        app.alerts.show({ type: 'success' }, t('key_created'));
        m.redraw();
      })
      .catch(() => {
        this.creating = false;
        app.alerts.show({ type: 'error' }, t('key_error'));
        m.redraw();
      });
  }

  private revoke(key: Key) {
    if (!confirm(t('confirm_revoke', { label: key.label }) as unknown as string)) return;
    app.request({ method: 'DELETE', url: `${this.api()}/connect-keys/${key.id}` }).then(() => {
      this.keys = this.keys.filter((k) => k.id !== key.id);
      this.subs = this.subs.filter((s) => s.keyId !== key.id);
      m.redraw();
    });
  }

  private copy(value: string) {
    navigator.clipboard?.writeText(value).then(() => app.alerts.show({ type: 'success' }, t('copied')));
  }

  content() {
    if (this.loading)
      return (
        <div className="ExtensionPage-settings">
          <div className="container">
            <LoadingIndicator />
          </div>
        </div>
      );

    return (
      <div className="ExtensionPage-settings ConnectAdmin">
        <div className="container">
          <p className="helpText">{t('intro')}</p>

          {/* Zapier — the headline in-admin automation experience */}
          {ZapierPanel.component({
            clientId: this.zapierClientId,
            appSlug: this.zapierAppSlug,
            saving: this.savingZapier,
            onSave: (clientId: string, appSlug: string) => this.saveZapier(clientId, appSlug),
          })}

          {this.rulesSection()}
          {this.createKeySection()}
          {this.keysSection()}
          {this.triggersSection()}
          {this.subscriptionsSection()}
        </div>
      </div>
    );
  }

  private newRuleButton() {
    return Button.component({ className: 'Button Button--primary', icon: 'fas fa-plus', onclick: () => this.newRule() }, t('new_rule_btn'));
  }

  private rulesSection(): Mithril.Children {
    return [
      <div className="ConnectAdmin-rulesHead">
        <h3>{t('rules_heading')}</h3>
        {!this.editing ? this.newRuleButton() : null}
      </div>,
      <p className="helpText">{t('rules_intro')}</p>,
      this.editing ? (
        RuleEditor.component({
          rule: this.editing,
          meta: this.meta || { events: this.events, actions: [], operators: [], groups: [], tags: [] },
          saving: this.savingRule,
          onSave: (r: RuleDraft) => this.saveRule(r),
          onCancel: () => {
            this.editing = null;
          },
        })
      ) : this.rules.length === 0 ? (
        <div className="ConnectAdmin-emptyRules">
          <p className="helpText">{t('no_rules')}</p>
          {this.newRuleButton()}
        </div>
      ) : (
        <table className="ConnectAdmin-table">
          <thead>
            <tr>
              <th>{t('col_rule')}</th>
              <th>{t('col_trigger')}</th>
              <th>{t('col_runs')}</th>
              <th>{t('col_on')}</th>
              <th />
            </tr>
          </thead>
          <tbody>{this.rules.map((r) => this.ruleRow(r))}</tbody>
        </table>
      ),
    ];
  }

  private ruleRow(r: Rule): Mithril.Children {
    return (
      <tr>
        <td>
          <b>{r.name}</b>
        </td>
        <td>
          <code>{r.event}</code>
        </td>
        <td>{r.runs}</td>
        <td>{Switch.component({ state: r.enabled, onchange: () => this.toggleRule(r) })}</td>
        <td className="ConnectAdmin-rowActions">
          {Button.component({
            className: 'Button Button--icon Button--flat',
            icon: 'fas fa-pen',
            onclick: () => {
              const { id, name, event, enabled, match, conditions, actions } = r;
              this.editing = { id, name, event, enabled, match, conditions, actions };
            },
          })}
          {Button.component({
            className: 'Button Button--icon Button--flat ConnectAdmin-revoke',
            icon: 'fas fa-trash',
            onclick: () => this.deleteRule(r),
          })}
        </td>
      </tr>
    );
  }

  private scopeCheckbox(scope: 'read' | 'write'): Mithril.Children {
    return (
      <label className="checkbox">
        <input
          type="checkbox"
          checked={this.newScopes[scope]}
          onchange={(e: any) => {
            this.newScopes[scope] = e.target.checked;
          }}
        />{' '}
        {t('scope_' + scope)}
      </label>
    );
  }

  private createKeySection(): Mithril.Children {
    return (
      <div className="ConnectAdmin-create">
        <h3>{t('new_key')}</h3>
        <div className="ConnectAdmin-createRow">
          <input
            className="FormControl"
            placeholder={extractText(t('label_placeholder'))}
            value={this.newLabel}
            oninput={(e: any) => {
              this.newLabel = e.target.value;
            }}
          />
          {this.scopeCheckbox('read')}
          {this.scopeCheckbox('write')}
          {Button.component({ className: 'Button Button--primary', loading: this.creating, onclick: () => this.create() }, t('create'))}
        </div>
      </div>
    );
  }

  private keysSection(): Mithril.Children {
    return [
      <h3>{t('keys_heading')}</h3>,
      this.keys.length === 0 ? (
        <p className="helpText">{t('no_keys')}</p>
      ) : (
        <table className="ConnectAdmin-table">
          <thead>
            <tr>
              <th>{t('col_label')}</th>
              <th>{t('col_actsas')}</th>
              <th>{t('col_scopes')}</th>
              <th>{t('col_creds')}</th>
              <th>{t('col_subs')}</th>
              <th />
            </tr>
          </thead>
          <tbody>{this.keys.map((k) => this.keyRow(k))}</tbody>
        </table>
      ),
    ];
  }

  private keyRow(k: Key): Mithril.Children {
    return (
      <tr>
        <td>
          <b>{k.label}</b>
        </td>
        <td>{k.user || '—'}</td>
        <td>{k.scopes.join(', ')}</td>
        <td className="ConnectAdmin-creds">
          {k.token ? (
            [
              <div className="ConnectAdmin-cred">
                <code>{k.token}</code>
                {Button.component({ className: 'Button Button--icon Button--flat', icon: 'far fa-copy', onclick: () => this.copy(k.token!) })}
              </div>,
              <p className="ConnectAdmin-once">{t('token_once')}</p>,
            ]
          ) : (
            <div className="ConnectAdmin-cred">
              <code>{(k.tokenPrefix || 'ck_') + '…'}</code>
            </div>
          )}
          <div className="ConnectAdmin-cred">
            <code>{this.revealed[k.id] ? k.secret : '•••••• ' + t('secret')}</code>
            {Button.component({
              className: 'Button Button--icon Button--flat',
              icon: this.revealed[k.id] ? 'far fa-eye-slash' : 'far fa-eye',
              onclick: () => {
                this.revealed[k.id] = !this.revealed[k.id];
              },
            })}
            {Button.component({ className: 'Button Button--icon Button--flat', icon: 'far fa-copy', onclick: () => this.copy(k.secret) })}
          </div>
        </td>
        <td>{k.hooks}</td>
        <td>
          {Button.component({
            className: 'Button Button--icon Button--flat ConnectAdmin-revoke',
            icon: 'fas fa-trash',
            onclick: () => this.revoke(k),
          })}
        </td>
      </tr>
    );
  }

  private triggersSection(): Mithril.Children {
    return [
      <h3>{t('triggers_heading')}</h3>,
      <ul className="ConnectAdmin-events">
        {this.events.map((e) => (
          <li>
            <code>{e.key}</code> <span>{t('events.' + e.key.replace('.', '_'))}</span>
          </li>
        ))}
      </ul>,
    ];
  }

  private subscriptionsSection(): Mithril.Children {
    return [
      <h3>{t('subs_heading')}</h3>,
      this.subs.length === 0 ? (
        <p className="helpText">{t('no_subs')}</p>
      ) : (
        <table className="ConnectAdmin-table">
          <thead>
            <tr>
              <th>{t('col_event')}</th>
              <th>{t('col_target')}</th>
              <th>{t('col_key')}</th>
            </tr>
          </thead>
          <tbody>
            {this.subs.map((s) => (
              <tr>
                <td>
                  <code>{s.event}</code>
                </td>
                <td className="ConnectAdmin-target">{s.targetUrl}</td>
                <td>{s.keyLabel || '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      ),
    ];
  }
}
