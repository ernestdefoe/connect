import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import type { ExtensionPageAttrs } from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';

const t = (key: string, params?: Record<string, unknown>) =>
  app.translator.trans(`ernestdefoe-connect.admin.${key}`, params);

interface Key {
  id: number; label: string; token: string; secret: string; scopes: string[];
  user: string | null; userId: number; hooks: number; lastUsedAt: string | null;
}
interface Sub { id: number; event: string; targetUrl: string; keyLabel: string | null; }
interface Evt { key: string; label: string; }

/**
 * Connect admin page: create/revoke API keys, see which triggers exist, and
 * watch live subscriptions. The keys carry the secrets an admin pastes into
 * Zapier/Make, so token + secret are copyable here.
 */
export default class ConnectPage extends ExtensionPage<ExtensionPageAttrs> {
  private loading = true;
  private keys: Key[] = [];
  private subs: Sub[] = [];
  private events: Evt[] = [];
  private newLabel = '';
  private newScopes: Record<string, boolean> = { read: true, write: true };
  private creating = false;
  private revealed: Record<number, boolean> = {};

  oninit(vnode: Mithril.Vnode<ExtensionPageAttrs, this>) {
    super.oninit(vnode);
    this.load();
  }

  private api() {
    return app.forum.attribute('apiUrl');
  }

  private load() {
    this.loading = true;
    Promise.all([
      app.request<any>({ method: 'GET', url: `${this.api()}/connect/keys` }),
      app.request<any>({ method: 'GET', url: `${this.api()}/connect/subscriptions` }),
      app.request<any>({ method: 'GET', url: `${this.api()}/connect/events` }),
    ])
      .then(([k, s, e]) => {
        this.keys = k.data || [];
        this.subs = s.data || [];
        this.events = e.data || [];
        this.loading = false;
        m.redraw();
      })
      .catch(() => { this.loading = false; m.redraw(); });
  }

  private create() {
    const label = this.newLabel.trim();
    if (!label || this.creating) return;
    const scopes = Object.keys(this.newScopes).filter((s) => this.newScopes[s]);
    this.creating = true;
    m.redraw();
    app.request<any>({
      method: 'POST',
      url: `${this.api()}/connect/keys`,
      body: { data: { label, scopes } },
    })
      .then((res) => {
        this.keys.unshift(res.data);
        this.revealed[res.data.id] = true; // show the fresh secret immediately
        this.newLabel = '';
        this.creating = false;
        app.alerts.show({ type: 'success' }, t('key_created'));
        m.redraw();
      })
      .catch(() => { this.creating = false; app.alerts.show({ type: 'error' }, t('key_error')); m.redraw(); });
  }

  private revoke(key: Key) {
    if (!confirm(t('confirm_revoke', { label: key.label }) as unknown as string)) return;
    app.request({ method: 'DELETE', url: `${this.api()}/connect/keys/${key.id}` }).then(() => {
      this.keys = this.keys.filter((k) => k.id !== key.id);
      this.subs = this.subs.filter((s) => s.keyLabel !== key.label);
      m.redraw();
    });
  }

  private copy(value: string) {
    navigator.clipboard?.writeText(value).then(() => app.alerts.show({ type: 'success' }, t('copied')));
  }

  content() {
    if (this.loading) return <div className="ExtensionPage-settings"><div className="container"><LoadingIndicator /></div></div>;

    return (
      <div className="ExtensionPage-settings ConnectAdmin">
        <div className="container">
          <p className="helpText">{t('intro')}</p>

          {/* Create */}
          <div className="ConnectAdmin-create">
            <h3>{t('new_key')}</h3>
            <div className="ConnectAdmin-createRow">
              <input className="FormControl" placeholder={t('label_placeholder') as string}
                value={this.newLabel} oninput={(e: any) => { this.newLabel = e.target.value; }} />
              <label className="checkbox"><input type="checkbox" checked={this.newScopes.read}
                onchange={(e: any) => { this.newScopes.read = e.target.checked; }} /> {t('scope_read')}</label>
              <label className="checkbox"><input type="checkbox" checked={this.newScopes.write}
                onchange={(e: any) => { this.newScopes.write = e.target.checked; }} /> {t('scope_write')}</label>
              {Button.component({ className: 'Button Button--primary', loading: this.creating, onclick: () => this.create() }, t('create'))}
            </div>
          </div>

          {/* Keys */}
          <h3>{t('keys_heading')}</h3>
          {this.keys.length === 0 ? (
            <p className="helpText">{t('no_keys')}</p>
          ) : (
            <table className="ConnectAdmin-table">
              <thead><tr>
                <th>{t('col_label')}</th><th>{t('col_actsas')}</th><th>{t('col_scopes')}</th>
                <th>{t('col_creds')}</th><th>{t('col_subs')}</th><th />
              </tr></thead>
              <tbody>
                {this.keys.map((k) => (
                  <tr>
                    <td><b>{k.label}</b></td>
                    <td>{k.user || '—'}</td>
                    <td>{k.scopes.join(', ')}</td>
                    <td className="ConnectAdmin-creds">
                      <div className="ConnectAdmin-cred">
                        <code>{k.token}</code>
                        {Button.component({ className: 'Button Button--icon Button--flat', icon: 'far fa-copy', onclick: () => this.copy(k.token) })}
                      </div>
                      <div className="ConnectAdmin-cred">
                        <code>{this.revealed[k.id] ? k.secret : '•••••• ' + t('secret')}</code>
                        {Button.component({ className: 'Button Button--icon Button--flat', icon: this.revealed[k.id] ? 'far fa-eye-slash' : 'far fa-eye', onclick: () => { this.revealed[k.id] = !this.revealed[k.id]; } })}
                        {Button.component({ className: 'Button Button--icon Button--flat', icon: 'far fa-copy', onclick: () => this.copy(k.secret) })}
                      </div>
                    </td>
                    <td>{k.hooks}</td>
                    <td>{Button.component({ className: 'Button Button--icon Button--flat ConnectAdmin-revoke', icon: 'fas fa-trash', onclick: () => this.revoke(k) })}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}

          {/* Triggers */}
          <h3>{t('triggers_heading')}</h3>
          <ul className="ConnectAdmin-events">
            {this.events.map((e) => <li><code>{e.key}</code> <span>{e.label}</span></li>)}
          </ul>

          {/* Subscriptions */}
          <h3>{t('subs_heading')}</h3>
          {this.subs.length === 0 ? (
            <p className="helpText">{t('no_subs')}</p>
          ) : (
            <table className="ConnectAdmin-table">
              <thead><tr><th>{t('col_event')}</th><th>{t('col_target')}</th><th>{t('col_key')}</th></tr></thead>
              <tbody>
                {this.subs.map((s) => (
                  <tr><td><code>{s.event}</code></td><td className="ConnectAdmin-target">{s.targetUrl}</td><td>{s.keyLabel || '—'}</td></tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    );
  }
}
