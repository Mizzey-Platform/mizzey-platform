"""Which stories get full validation and which get a light pass.

Risk-weighted, per the design: the five custom epics plus every story flagged
key. That is 73 of the 205, and it is where the effort and the risk are.
"""

from discovery.schema import CUSTOM_EPICS


def risk_set(stories):
    """Story ids requiring full validation."""
    return {s['id'] for s in stories
            if s['epic'] in CUSTOM_EPICS or s.get('key')}


def light_set(stories):
    """Story ids requiring only a confirmation that native is true."""
    risky = risk_set(stories)
    return {s['id'] for s in stories if s['id'] not in risky}
