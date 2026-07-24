import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';

const t = (key: string, params?: Record<string, unknown>) =>
  app.translator.trans(`ernestdefoe-connect.admin.${key}`, params);

// Zapier's official embeddable web components ("Zapier Elements"). Loading these
// gives us the same in-admin browse/create/manage experience Invision ships —
// the admin never leaves the forum to wire up "post new discussions to a
// Facebook Page", Slack, Discord, etc.
const SDK_JS = 'https://cdn.zapier.com/packages/partner-sdk/v0/zapier-elements/zapier-elements.esm.js';
const SDK_CSS = 'https://cdn.zapier.com/packages/partner-sdk/v0/zapier-elements/zapier-elements.css';

// Popular destinations, shown as one-click recipe tiles. `slug` is Zapier's app
// slug used to filter Zap templates; `icon` is a FontAwesome brand glyph.
const POPULAR: { slug: string; label: string; icon: string }[] = [
  { slug: 'facebook-pages', label: 'Facebook Pages', icon: 'fab fa-facebook' },
  { slug: 'slack', label: 'Slack', icon: 'fab fa-slack' },
  { slug: 'discord', label: 'Discord', icon: 'fab fa-discord' },
  { slug: 'twitter', label: 'X / Twitter', icon: 'fab fa-twitter' },
  { slug: 'google-sheets', label: 'Google Sheets', icon: 'fab fa-google' },
  { slug: 'mailchimp', label: 'Mailchimp', icon: 'fab fa-mailchimp' },
  { slug: 'telegram', label: 'Telegram', icon: 'fab fa-telegram' },
  { slug: 'trello', label: 'Trello', icon: 'fab fa-trello' },
];

interface Attrs {
  clientId: string;
  appSlug: string;
  saving: boolean;
  onSave: (clientId: string, appSlug: string) => void;
}

export default class ZapierPanel extends Component<Attrs> {
  private draftClientId = '';
  private draftAppSlug = '';
  private editing = false;
  private selected = POPULAR[0].slug;
  private sdkLoading = false;
  private sdkReady = false;

  oninit(vnode: Mithril.Vnode<Attrs, this>) {
    super.oninit(vnode);
    this.draftClientId = this.attrs.clientId || '';
    this.draftAppSlug = this.attrs.appSlug || '';
    this.editing = !this.attrs.clientId; // no client id yet → show setup
    if (this.attrs.clientId) this.loadSdk();
  }

  /** Inject Zapier's Elements script + stylesheet exactly once. */
  private loadSdk() {
    if (this.sdkReady || this.sdkLoading) return;
    this.sdkLoading = true;

    if (!document.querySelector(`link[href="${SDK_CSS}"]`)) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = SDK_CSS;
      document.head.appendChild(link);
    }
    if (customElements.get('zapier-full-experience')) {
      this.sdkLoading = false;
      this.sdkReady = true;
      return;
    }
    const s = document.createElement('script');
    s.type = 'module';
    s.src = SDK_JS;
    s.onload = () => { this.sdkLoading = false; this.sdkReady = true; m.redraw(); };
    s.onerror = () => { this.sdkLoading = false; m.redraw(); };
    document.head.appendChild(s);
  }

  // Match the embed to the admin's actual theme by reading the page background's
  // luminance — framework-agnostic, so it works whatever Flarum calls dark mode.
  private theme(): string {
    try {
      const bg = getComputedStyle(document.body).backgroundColor;
      const m = bg.match(/\d+(\.\d+)?/g);
      if (m && m.length >= 3) {
        const [r, g, b] = m.map(Number);
        const lum = 0.2126 * r + 0.7152 * g + 0.0722 * b;
        return lum < 128 ? 'dark' : 'light';
      }
    } catch (e) {
      // fall through
    }
    return 'light';
  }

  private appsFilter(): string {
    // Scope templates to Connect + the picked destination when we know our slug.
    return [this.attrs.appSlug, this.selected].filter(Boolean).join(',');
  }

  view() {
    const configured = !!this.attrs.clientId;

    return (
      <div className="ConnectZapier">
        <div className="ConnectZapier-head">
          <div>
            <h3><i className="fas fa-bolt" /> {t('zapier_heading')}</h3>
            <p className="helpText">{t('zapier_intro')}</p>
          </div>
          {configured
            ? Button.component({ className: 'Button Button--flat', icon: 'fas fa-cog', onclick: () => { this.editing = !this.editing; } }, t('zapier_settings_btn'))
            : null}
        </div>

        {this.editing ? this.setup() : null}

        {configured ? this.embed() : (!this.editing ? this.setup() : null)}
      </div>
    );
  }

  /**
   * Setup state. Leads with what actually works today (an API key + forum URL is
   * all Zapier needs), because the embedded experience below is gated on Zapier
   * issuing a Client ID — which only happens once the integration is published
   * publicly in their App Directory.
   */
  private setup(): Mithril.Children {
    return (
      <div className="ConnectZapier-setup">
        <div className="ConnectZapier-today">
          <h4>{t('zapier_today_heading')}</h4>
          <p className="helpText">{t('zapier_today_body')}</p>
        </div>

        <h4>{t('zapier_embed_heading')}</h4>
        <p className="helpText">{t('zapier_embed_requires')}</p>
        <div className="ConnectZapier-setupRow">
          <label>
            {t('zapier_client_id')}
            <input className="FormControl" placeholder={t('zapier_client_id_placeholder') as string} value={this.draftClientId}
              oninput={(e: any) => { this.draftClientId = e.target.value; }} />
          </label>
          <label>
            {t('zapier_app_slug')}
            <input className="FormControl" placeholder="connect-for-flarum" value={this.draftAppSlug}
              oninput={(e: any) => { this.draftAppSlug = e.target.value; }} />
          </label>
          {Button.component({
            className: 'Button Button--primary', loading: this.attrs.saving,
            onclick: () => {
              this.attrs.onSave(this.draftClientId.trim(), this.draftAppSlug.trim());
              this.editing = false;
              if (this.draftClientId.trim()) this.loadSdk();
            },
          }, t('zapier_save'))}
        </div>
        <p className="helpText">{t('zapier_client_id_help')}</p>
      </div>
    );
  }

  /** The live embedded experience: recipe tiles + templates + full builder. */
  private embed(): Mithril.Children {
    if (!this.sdkReady) {
      return <div className="ConnectZapier-loading"><LoadingIndicator /> <span>{t('zapier_loading')}</span></div>;
    }

    const theme = this.theme();
    const clientId = this.attrs.clientId;

    return (
      <div className="ConnectZapier-embed">
        <h4>{t('zapier_recipes')}</h4>
        <div className="ConnectZapier-tiles">
          {POPULAR.map((p) => (
            <button
              className={'ConnectZapier-tile' + (this.selected === p.slug ? ' is-active' : '')}
              onclick={() => { this.selected = p.slug; }}
              type="button"
            >
              <i className={p.icon} />
              <span>{p.label}</span>
            </button>
          ))}
        </div>

        {m('zapier-zap-templates', {
          'client-id': clientId,
          theme,
          apps: this.appsFilter(),
          limit: 6,
          'use-this-zap': 'show',
          'zap-style': 'row',
          'create-without-template': 'hide',
        })}

        <h4>{t('zapier_build_anything')}</h4>
        <p className="helpText">{t('zapier_build_anything_help')}</p>
        {m('zapier-full-experience', {
          'client-id': clientId,
          theme,
          'app-search-bar-display': 'show',
          'intro-copy-display': 'hide',
        })}
      </div>
    );
  }
}
