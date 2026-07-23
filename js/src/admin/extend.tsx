import Extend from 'flarum/common/extenders';
import ConnectPage from './components/ConnectPage';

export default [
  new Extend.Admin().page(ConnectPage),
];
